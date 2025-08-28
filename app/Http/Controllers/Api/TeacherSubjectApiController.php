<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Models\ActividadGeneral;

class TeacherSubjectApiController extends Controller
{
    /* ========== GET /v1/profesores/{profesor_id}/materias ==========
       Devuelve:
       - materias_asignadas (del profe)
       - grupos_disponibles (según rol del usuario autenticado)
    */
    public function index(Request $request, $profesor_id)
    {
        $profesor = DB::table('teachers')->where('teacher_id', (int)$profesor_id)->first();
        if (!$profesor) return response()->json(['message'=>'Profesor no encontrado'], 404);

        // Materias ya asignadas al profe
        $materiasAsignadas = DB::table('teacher_subjects AS ts')
            ->join('subjects AS s', 's.subject_id', '=', 'ts.subject_id')
            ->leftJoin('groups as g', 'g.group_id', '=', 'ts.group_id')
            ->leftJoin('programs as p', 'p.program_id', '=', 'g.program_id')
            ->where('ts.teacher_id', (int)$profesor_id)
            ->select([
                'ts.teacher_subject_id',
                's.subject_id',
                's.subject_name',
                's.weekly_hours',
                'ts.group_id',
                'g.group_name',
                'p.program_name',
                'p.area',
            ])
            ->orderBy('s.subject_name')
            ->get();

        // Grupos visibles por rol
        $grupos = $this->gruposVisiblesPorUsuario($request->user());

        return response()->json([
            'profesor'            => $profesor,
            'materias_asignadas'  => $materiasAsignadas,
            'grupos_disponibles'  => $grupos,
        ]);
    }

    /* ========== POST /v1/profesores/{profesor_id}/materias ==========
       Cuerpo:
       - materias_asignadas: int[]
       - grupos_asignados:   int[]
    */
    public function store(Request $request, $profesor_id)
    {
        $user = $request->user();
        if (!$user || !$user->can('asignar materias')) {
            return response()->json(['message' => 'Sin permiso para asignar materias'], 403);
        }

        $profesor = DB::table('teachers')->where('teacher_id', (int)$profesor_id)->first();
        if (!$profesor) return response()->json(['message'=>'Profesor no encontrado'], 404);

        $request->validate([
            'materias_asignadas' => 'nullable|array',
            'materias_asignadas.*' => 'integer',
            'grupos_asignados'   => 'required|array|min:1',
            'grupos_asignados.*' => 'integer',
        ], [
            'grupos_asignados.required' => 'Seleccione al menos un grupo.',
        ]);

        $materiaIds = $request->input('materias_asignadas', []);
        $grupoIds   = array_values(array_filter($request->input('grupos_asignados', []), 'is_numeric'));

        if (empty($grupoIds)) {
            return response()->json(['message' => 'Faltan datos para asignar'], 422);
        }

        // Filtro por rol (subdirector: solo su(s) área(s))
        $permitidos = $this->idsGruposVisibles($user);
        foreach ($grupoIds as $gid) {
            if (!in_array((int)$gid, $permitidos, true)) {
                return response()->json(['message' => "No tienes permiso para asignar en el grupo {$gid}"], 403);
            }
        }

        // Config horarios
        $horarios_disponibles = config('horarios.disponibles', []);
        $dias_semana          = config('horarios.dias_semana', []);

        DB::beginTransaction();
        try {
            // Disponibilidad del profe
            $teacherAvailability = $this->cargarDisponibilidadProfesor((int)$profesor_id);

            // horas por materia
            $mapHours = [];
            if (!empty($materiaIds)) {
                $rows = DB::table('subjects')
                    ->whereIn('subject_id', $materiaIds)
                    ->select('subject_id','weekly_hours')
                    ->get();
                foreach ($rows as $r) $mapHours[$r->subject_id] = (int)$r->weekly_hours;
            }

            $errores = [];

            foreach ($grupoIds as $g) {
                foreach ($materiaIds as $m) {
                    $wh = (int)($mapHours[$m] ?? 0);
                    if ($wh <= 0) {
                        $errores[] = "La materia $m no tiene horas > 0";
                        continue;
                    }

                    $ok = $this->asignarMateriaConBloquesYAparteSiSobra(
                        (int)$profesor_id, (int)$m, (int)$g, $wh, $errores,
                        $horarios_disponibles, $dias_semana, $teacherAvailability
                    );

                    if (!$ok) continue;

                    // relación teacher_subjects (si no existe)
                    $exists = DB::table('teacher_subjects')
                        ->where('teacher_id', (int)$profesor_id)
                        ->where('subject_id', (int)$m)
                        ->where('group_id',   (int)$g)
                        ->exists();

                    if (!$exists) {
                        DB::table('teacher_subjects')->insert([
                            'teacher_id'       => (int)$profesor_id,
                            'subject_id'       => (int)$m,
                            'group_id'         => (int)$g,
                            'fyh_creacion'     => now(),
                            'fyh_actualizacion'=> now(),
                        ]);
                    }
                }
            }

            if (!empty($errores)) {
                DB::rollBack();
                return response()->json(['message'=>'Validación', 'errors'=>$errores], 422);
            }

            // actualizar total de horas del profe
            $total = DB::table('teacher_subjects AS ts')
                ->join('subjects AS s', 's.subject_id', '=', 'ts.subject_id')
                ->where('ts.teacher_id', (int)$profesor_id)
                ->sum('s.weekly_hours');

            DB::table('teachers')
                ->where('teacher_id', (int)$profesor_id)
                ->update([
                    'hours'             => (int)$total,
                    'fyh_actualizacion' => now(),
                ]);

            ActividadGeneral::registrar('ASIGNAR', 'teacher_subjects', (int)$profesor_id, "Asignó materias/grupos al profe {$profesor_id}");

            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Asignación exitosa']);
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Error BD al asignar materias', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return response()->json(['message'=>'Error de base de datos al asignar materias'], 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error inesperado al asignar materias', ['msg'=>$e->getMessage()]);
            return response()->json(['message'=>'Ocurrió un error inesperado al asignar materias'], 500);
        }
    }

    /* ========== DELETE /v1/profesores/{profesor_id}/materias/{teacher_subject_id} ========== */
    public function destroy(Request $request, $profesor_id, $teacher_subject_id)
    {
        $user = $request->user();
        if (!$user || !$user->can('asignar materias')) {
            return response()->json(['message' => 'Sin permiso'], 403);
        }

        $rel = DB::table('teacher_subjects')
            ->where('teacher_subject_id', (int)$teacher_subject_id)
            ->where('teacher_id', (int)$profesor_id)
            ->first();

        if (!$rel) {
            return response()->json(['message' => 'Relación no encontrada'], 404);
        }

        DB::beginTransaction();
        try {
            // Liberar bloques en schedule_assignments (dejar teacher_id en null)
            DB::table('schedule_assignments')
                ->where('teacher_id', (int)$profesor_id)
                ->where('subject_id', (int)$rel->subject_id)
                ->where('group_id',   (int)$rel->group_id)
                ->update([
                    'teacher_id'       => null,
                    'fyh_actualizacion'=> now(),
                ]);

            DB::table('teacher_subjects')
                ->where('teacher_subject_id', (int)$teacher_subject_id)
                ->delete();

            // Actualizar horas del profe
            $total = DB::table('teacher_subjects AS ts')
                ->join('subjects AS s', 's.subject_id', '=', 'ts.subject_id')
                ->where('ts.teacher_id', (int)$profesor_id)
                ->sum('s.weekly_hours');

            DB::table('teachers')
                ->where('teacher_id', (int)$profesor_id)
                ->update([
                    'hours'             => (int)$total,
                    'fyh_actualizacion' => now(),
                ]);

            DB::commit();
            return response()->json(['ok'=>true]);
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Error BD al eliminar relación teacher_subject', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return response()->json(['message'=>'Error de base de datos al eliminar la asignación'], 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error inesperado al eliminar relación teacher_subject', ['msg'=>$e->getMessage()]);
            return response()->json(['message'=>'Ocurrió un error inesperado al eliminar la asignación'], 500);
        }
    }

    /* ========== POST /v1/profesores/{profesor_id}/ajax/materias-por-grupo ==========
       Entrada: { group_id: int }
       Salida:  [{subject_id, subject_name, weekly_hours}] (JSON)
       Con filtro de área para Subdirector.
    */
    public function materiasPorGrupo(Request $request, $profesor_id)
    {
        $user = $request->user();
        if (!$user || !$user->can('asignar materias')) {
            return response()->json(['message' => 'Sin permiso'], 403);
        }

        $groupId = (int) $request->input('group_id');
        if (!$groupId) return response()->json([]);

        // Checa permiso por área/grupo
        $permitidos = $this->idsGruposVisibles($user);
        if (!in_array($groupId, $permitidos, true)) {
            return response()->json(['message' => 'No tienes permiso para ver ese grupo'], 403);
        }

        $group = DB::table('groups')->where('group_id', $groupId)->first();
        if (!$group) return response()->json([]);

        $materias = DB::table('program_term_subjects AS pts')
            ->join('subjects AS s', 's.subject_id', '=', 'pts.subject_id')
            ->where('pts.program_id', $group->program_id)
            ->where('pts.term_id',    $group->term_id)
            // ← SOLO materias no tomadas por ningún profe en ESTE grupo
            ->whereNotExists(function ($q) use ($groupId) {
                $q->select(DB::raw(1))
                  ->from('teacher_subjects as ts')
                  ->whereColumn('ts.subject_id', 'pts.subject_id')
                  ->where('ts.group_id', $groupId);
            })
            ->select('s.subject_id','s.subject_name','s.weekly_hours')
            ->distinct()
            ->orderBy('s.subject_name')
            ->get();

        return response()->json($materias);
    }

    /* ========== POST /v1/profesores/{profesor_id}/ajax/horas ==========
       Devuelve { total: int }
    */
    public function horasProfesor(Request $request, $profesor_id)
    {
        $total = DB::table('teacher_subjects AS ts')
            ->join('subjects AS s', 's.subject_id', '=', 'ts.subject_id')
            ->where('ts.teacher_id', (int)$profesor_id)
            ->sum('s.weekly_hours');

        return response()->json(['total' => (int)($total ?? 0)]);
    }

    /* ====================== HELPERS (clonan tu PHP) ====================== */

    private function gruposVisiblesPorUsuario($user)
    {
        $isAdmin       = $user?->hasRole('Administrador') ?? false;
        $isSubdirector = $user?->hasRole('Subdirector')   ?? false;

        $q = DB::table('groups as g')
            ->join('programs as p', 'p.program_id', '=', 'g.program_id')
            ->select([
                'g.group_id','g.group_name','g.program_id','g.term_id','g.turn_id',
                'p.program_name','p.area'
            ])
            // ← SOLO grupos con alguna materia aún libre
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('program_term_subjects as pts')
                  ->whereColumn('pts.program_id', 'g.program_id')
                  ->whereColumn('pts.term_id', 'g.term_id')
                  ->whereNotExists(function ($qq) {
                      $qq->select(DB::raw(1))
                         ->from('teacher_subjects as ts')
                         ->whereColumn('ts.subject_id', 'pts.subject_id')
                         ->whereColumn('ts.group_id', 'g.group_id');
                  });
            })
            ->orderBy('g.group_name');

        if ($isAdmin) {
            return $q->get();
        }

        if ($isSubdirector) {
            $areas = collect(explode(',', (string)($user->area ?? '')))
                ->map(fn($a)=>trim($a))->filter()->values()->all();
            if (empty($areas)) return collect([]);
            return $q->whereIn('p.area', $areas)->get();
        }

        return collect([]);
    }

    private function idsGruposVisibles($user): array
    {
        return $this->gruposVisiblesPorUsuario($user)->pluck('group_id')->map(fn($x)=>(int)$x)->all();
    }

    private function cargarDisponibilidadProfesor(int $teacherId): array
    {
        $rows = DB::table('teacher_availability')
            ->where('teacher_id', $teacherId)
            ->select('day_of_week','start_time','end_time')
            ->get();

        $disp = [];
        foreach ($rows as $r) {
            $d = $r->day_of_week;
            $disp[$d] = $disp[$d] ?? [];
            $disp[$d][] = ['start'=>$r->start_time, 'end'=>$r->end_time];
        }
        return $disp;
    }

    private function profesorEstaDisponible(array $availability, string $dia, string $s, string $e): bool
    {
        if (!isset($availability[$dia])) return false;
        $bS = strtotime($s); $bE = strtotime($e);
        foreach ($availability[$dia] as $rng) {
            $rS = strtotime($rng['start']); $rE = strtotime($rng['end']);
            if ($bS >= $rS && $bE <= $rE) return true;
        }
        return false;
    }

    private function teacherLibreEnHorario(int $teacherId, string $dia, string $s, string $e): bool
    {
        if (!$teacherId) return true;

        $count = DB::table('schedule_assignments')
            ->where('teacher_id', $teacherId)
            ->where('schedule_day', $dia)
            ->where(function ($q) use ($e,$s) {
                $q->where('start_time', '<', $e)
                  ->where('end_time',   '>', $s);
            })
            ->count();

        return $count === 0;
    }

    private function existeAsignacionManualDia(int $subjectId, int $groupId, string $dia): bool
    {
        $c = DB::table('manual_schedule_assignments')
            ->where('subject_id', $subjectId)
            ->where('group_id',   $groupId)
            ->where('schedule_day',$dia)
            ->where('estado','activo')
            ->count();
        return $c > 0;
    }

    private function copiarBloquesManualASchedule(
        int $teacherId,
        int $subjectId,
        int $groupId,
        array $availability
    ) {
        $rows = DB::table('manual_schedule_assignments')
            ->select('schedule_day','start_time','end_time','classroom_id','lab1_assigned AS lab_id','tipo_espacio')
            ->where('subject_id', $subjectId)
            ->where('group_id',   $groupId)
            ->where('estado','activo')
            ->get();

        if ($rows->isEmpty()) return 0;

        $horas = 0;

        foreach ($rows as $r) {
            $dia = $r->schedule_day; $s = $r->start_time; $e = $r->end_time;

            if (!$this->profesorEstaDisponible($availability, $dia, $s, $e)) return false;
            if (!$this->teacherLibreEnHorario($teacherId, $dia, $s, $e))   return false;

            $exists = DB::table('schedule_assignments')
                ->where('subject_id', $subjectId)
                ->where('group_id',   $groupId)
                ->where('schedule_day',$dia)
                ->where('start_time', $s)
                ->where('end_time',   $e)
                ->exists();

            if ($exists) {
                DB::table('schedule_assignments')
                    ->where('subject_id', $subjectId)
                    ->where('group_id',   $groupId)
                    ->where('schedule_day',$dia)
                    ->where('start_time', $s)
                    ->where('end_time',   $e)
                    ->update([
                        'teacher_id'       => $teacherId,
                        'fyh_actualizacion'=> now(),
                    ]);
            } else {
                DB::table('schedule_assignments')->insert([
                    'subject_id'     => $subjectId,
                    'group_id'       => $groupId,
                    'teacher_id'     => $teacherId,
                    'classroom_id'   => $r->classroom_id,
                    'lab_id'         => $r->lab_id,
                    'schedule_day'   => $dia,
                    'start_time'     => $s,
                    'end_time'       => $e,
                    'estado'         => 'activo',
                    'fyh_creacion'   => now(),
                    'tipo_espacio'   => $r->tipo_espacio ?: 'Laboratorio',
                ]);
            }

            $horas += (strtotime($e) - strtotime($s))/3600;
        }

        return $horas;
    }

    private function checarTodosBloquesExistentes(
        int $teacherId,
        int $subjectId,
        int $groupId,
        array $availability
    ) {
        $rows = DB::table('schedule_assignments')
            ->select('assignment_id','schedule_day','start_time','end_time')
            ->where('subject_id', $subjectId)
            ->where('group_id',   $groupId)
            ->where(function($q){
                $q->whereNull('teacher_id')->orWhere('teacher_id', 0);
            })
            ->where('estado','activo')
            ->orderBy('schedule_day')->orderBy('start_time')
            ->get();

        if ($rows->isEmpty()) return 0;

        foreach ($rows as $r) {
            if (!$this->profesorEstaDisponible($availability, $r->schedule_day, $r->start_time, $r->end_time)) return false;
            if (!$this->teacherLibreEnHorario($teacherId, $r->schedule_day, $r->start_time, $r->end_time))   return false;
        }

        return $rows->count();
    }

    private function asignarTodosBloquesExistentes(int $teacherId, int $subjectId, int $groupId): void
    {
        DB::table('schedule_assignments')
            ->where('subject_id', $subjectId)
            ->where('group_id',   $groupId)
            ->where(function($q){
                $q->whereNull('teacher_id')->orWhere('teacher_id', 0);
            })
            ->where('estado','activo')
            ->update([
                'teacher_id'       => $teacherId,
                'fyh_actualizacion'=> now()
            ]);
    }

    private function asignarBloqueHorario(
        int $teacherId,
        int $subjectId,
        int $groupId,
        $classroomId,
        string $dia,
        int $startTs,
        int $endTs,
        array $availability
    ): bool {
        $s = date('H:i:s', $startTs);
        $e = date('H:i:s', $endTs);

        if (!$this->profesorEstaDisponible($availability, $dia, $s, $e)) return false;

        // choque con cualquier materia del grupo
        $c1 = DB::table('schedule_assignments')
            ->where('group_id', $groupId)
            ->where('schedule_day', $dia)
            ->where('start_time','<',$e)
            ->where('end_time','>',$s)
            ->count();
        if ($c1 > 0) return false;

        // choque con horario del profe
        if ($teacherId && !$this->teacherLibreEnHorario($teacherId, $dia, $s, $e)) return false;

        DB::table('schedule_assignments')->insert([
            'subject_id'    => $subjectId,
            'group_id'      => $groupId,
            'teacher_id'    => $teacherId,
            'classroom_id'  => $classroomId,
            'schedule_day'  => $dia,
            'start_time'    => $s,
            'end_time'      => $e,
            'estado'        => 'activo',
            'fyh_creacion'  => now(),
            'tipo_espacio'  => 'Aula',
        ]);

        return true;
    }

    private function asignarMateriaConBloquesYAparteSiSobra(
        int $teacherId,
        int $subjectId,
        int $groupId,
        int $weeklyHours,
        array &$errores,
        array $horarios_disponibles,
        array $dias_semana,
        array $availability
    ) {
        $gi = DB::table('groups')->where('group_id', $groupId)->first(['classroom_assigned','turn_id']);
        if (!$gi) {
            $errores[] = "No existe grupo $groupId";
            return false;
        }
        $classroomId = $gi->classroom_assigned ?: null;

        $mapTurno = [
            1=>'MATUTINO', 2=>'VESPERTINO', 3=>'MIXTO', 4=>'ZINAPÉCUARO',
            5=>'ENFERMERIA', 6=>'MATUTINO AVANZADO', 7=>'VESPERTINO AVANZADO'
        ];
        $turno = $mapTurno[$gi->turn_id] ?? 'MATUTINO';

        if (!isset($dias_semana[$turno]) || !isset($horarios_disponibles[$turno])) {
            $errores[] = "No hay configuración de días/horarios para turno $turno";
            return false;
        }

        // 1) copiar bloques manuales
        $hManual = $this->copiarBloquesManualASchedule($teacherId, $subjectId, $groupId, $availability);
        if ($hManual === false) {
            $errores[] = "El profesor no puede cubrir los bloques manuales de la materia $subjectId en el grupo $groupId.";
            return false;
        }
        $weeklyHours -= (int)$hManual;
        if ($weeklyHours <= 0) return true;

        // 2) bloques existentes sin profe
        $cnt = $this->checarTodosBloquesExistentes($teacherId, $subjectId, $groupId, $availability);
        if ($cnt === false) {
            $errores[] = "No se asignó materia $subjectId al grupo $groupId: el profesor no puede cubrir todos los bloques existentes.";
            return false;
        }
        if ($cnt > 0) {
            if ($cnt > $weeklyHours) {
                $errores[] = "La materia $subjectId requiere $weeklyHours horas, pero hay $cnt bloques existentes. Ajusta manualmente.";
                return false;
            }
            $this->asignarTodosBloquesExistentes($teacherId, $subjectId, $groupId);
            $weeklyHours -= $cnt;
        }
        if ($weeklyHours <= 0) return true;

        // 3) completar huecos de 1h
        $diasTurno = $dias_semana[$turno];
        $dc = count($diasTurno);
        $i = 0;
        $ciclosSinHueco = 0;

        while ($weeklyHours > 0) {
            $dia = $diasTurno[$i];

            if ($this->existeAsignacionManualDia($subjectId, $groupId, $dia)) {
                $i = ($i + 1) % $dc;
                if (++$ciclosSinHueco >= $dc * 3) {
                    $errores[] = "No hay espacio para completar horas de la materia $subjectId en el grupo $groupId (evitar mismo día).";
                    return false;
                }
                continue;
            }

            if (!isset($horarios_disponibles[$turno][$dia])) {
                $i = ($i + 1) % $dc;
                continue;
            }

            $slots = $horarios_disponibles[$turno][$dia];
            if (isset($slots['start'])) $slots = [$slots];

            $hueco = false;
            foreach ($slots as $slot) {
                $ini = strtotime($slot['start']);
                $fin = strtotime($slot['end']);

                for ($ha = $ini; $ha + 3600 <= $fin; $ha += 3600) {
                    $ok = $this->asignarBloqueHorario(
                        $teacherId, $subjectId, $groupId, $classroomId, $dia, $ha, $ha + 3600, $availability
                    );

                    if ($ok) {
                        $weeklyHours--;
                        $hueco = true;
                        $ciclosSinHueco = 0;
                        break 2;
                    }
                }
            }

            if (!$hueco) {
                if (++$ciclosSinHueco >= $dc * 3) {
                    $errores[] = "No hay espacio para completar horas de la materia $subjectId en el grupo $groupId.";
                    return false;
                }
            }

            $i = ($i + 1) % $dc;
        }

        return true;
    }
}
