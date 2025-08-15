<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\ActividadGeneral;

class TeacherSubjectApiController extends Controller
{
    /**
     * Listar materias asignadas a un profesor (opcional ?group_id=)
     */
    public function index(Request $request, int $profesor_id)
    {
        $teacher = DB::table('teachers')->where('teacher_id', $profesor_id)->first();
        if (!$teacher) {
            return response()->json(['message' => 'Profesor no encontrado'], 404);
        }

        $q = DB::table('teacher_subjects as ts')
            ->join('subjects as s', 's.subject_id', '=', 'ts.subject_id')
            ->leftJoin('groups as g', 'g.group_id', '=', 'ts.group_id')
            ->where('ts.teacher_id', $profesor_id)
            ->select([
                'ts.teacher_subject_id',
                's.subject_id',
                's.subject_name',
                's.weekly_hours',
                'ts.group_id',
                'g.group_name',
            ])
            ->orderBy('s.subject_name');

        if ($request->filled('group_id')) {
            $q->where('ts.group_id', (int)$request->query('group_id'));
        }

        $asignaciones = $q->get();

        $horas = DB::table('teacher_subjects as ts')
            ->join('subjects as s', 's.subject_id', '=', 'ts.subject_id')
            ->where('ts.teacher_id', $profesor_id)
            ->sum('s.weekly_hours');

        return response()->json([
            'teacher' => [
                'teacher_id' => $teacher->teacher_id,
                'teacher_name' => $teacher->teacher_name,
                'clasificacion' => $teacher->clasificacion ?? null,
                'hours' => (int)($horas ?? 0),
            ],
            'items' => $asignaciones,
        ]);
    }

    /**
     * Asignar materias a uno o varios grupos para un profesor (bulk OK).
     * Cuerpo aceptado (uno u otro, ambos válidos):
     *  - { "materia_ids":[...], "grupo_ids":[...] }
     *  - { "materias_asignadas":[...], "grupos_asignados":[...] }  // espejo del web
     */
    public function store(Request $request, int $profesor_id)
    {
        $teacher = DB::table('teachers')->where('teacher_id', $profesor_id)->first();
        if (!$teacher) {
            return response()->json(['message' => 'Profesor no encontrado'], 404);
        }

        // Acepta dos nombres de arrays (compatibles con el web)
        $materiaIds = $request->input('materia_ids', $request->input('materias_asignadas', []));
        $grupoIds   = $request->input('grupo_ids',   $request->input('grupos_asignados',   []));

        $data = $request->validate([
            'materia_ids'        => 'nullable|array',
            'materia_ids.*'      => 'integer',
            'materias_asignadas' => 'nullable|array',
            'materias_asignadas.*' => 'integer',

            'grupo_ids'          => 'required_without:grupos_asignados|array|min:1',
            'grupo_ids.*'        => 'integer',
            'grupos_asignados'   => 'required_without:grupo_ids|array|min:1',
            'grupos_asignados.*' => 'integer',
        ], [
            'grupo_ids.required_without'        => 'Seleccione al menos un grupo.',
            'grupos_asignados.required_without' => 'Seleccione al menos un grupo.',
        ]);

        $materiaIds = array_values(array_filter((array)$materiaIds, 'is_numeric'));
        $grupoIds   = array_values(array_filter((array)$grupoIds,   'is_numeric'));

        if (empty($grupoIds)) {
            return response()->json(['message' => 'Debe seleccionar al menos un grupo'], 422);
        }

        // Config del sistema
        $horarios_disponibles = config('horarios.disponibles', []);
        $dias_semana          = config('horarios.dias_semana', []);
        if (empty($horarios_disponibles) || empty($dias_semana)) {
            return response()->json([
                'message' => 'No hay configuración de días/horarios; revisa config/horarios.php'
            ], 500);
        }

        DB::beginTransaction();

        try {
            // Disponibilidad del profe
            $teacherAvailability = $this->cargarDisponibilidadProfesor($profesor_id);

            // Mapa de horas por materia
            $mapHours = [];
            if (!empty($materiaIds)) {
                $rows = DB::table('subjects')
                    ->whereIn('subject_id', $materiaIds)
                    ->select('subject_id','weekly_hours')
                    ->get();
                foreach ($rows as $r) $mapHours[$r->subject_id] = (int)$r->weekly_hours;
            }

            $errores = [];
            $creados = 0;

            foreach ($grupoIds as $g) {
                foreach ($materiaIds as $m) {
                    $wh = (int)($mapHours[$m] ?? 0);
                    if ($wh <= 0) {
                        $errores[] = "La materia $m no tiene horas > 0";
                        continue;
                    }

                    $ok = $this->asignarMateriaConBloquesYAparteSiSobra(
                        $profesor_id,
                        (int) $m,
                        (int) $g,
                        $wh,
                        $errores,
                        $horarios_disponibles,
                        $dias_semana,
                        $teacherAvailability
                    );

                    if (!$ok) {
                        continue;
                    }

                    // Pivot teacher_subjects (si no existe)
                    $exists = DB::table('teacher_subjects')
                        ->where('teacher_id', $profesor_id)
                        ->where('subject_id', $m)
                        ->where('group_id',   $g)
                        ->exists();

                    if (!$exists) {
                        DB::table('teacher_subjects')->insert([
                            'teacher_id'       => $profesor_id,
                            'subject_id'       => $m,
                            'group_id'         => $g,
                            'fyh_creacion'     => now(),
                            'fyh_actualizacion'=> now(),
                            'estado'           => 'ACTIVO',
                        ]);
                        $creados++;
                    }
                }
            }

            if (!empty($errores)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No se pudo completar la asignación.',
                    'errors'  => $errores,
                ], 409);
            }

            // Actualizar total de horas del profe
            $total = DB::table('teacher_subjects AS ts')
                ->join('subjects AS s', 's.subject_id', '=', 'ts.subject_id')
                ->where('ts.teacher_id', $profesor_id)
                ->sum('s.weekly_hours');

            DB::table('teachers')
                ->where('teacher_id', $profesor_id)
                ->update([
                    'hours'             => (int)($total ?? 0),
                    'fyh_actualizacion' => now(),
                ]);

            ActividadGeneral::registrar('ASIGNAR', 'teacher_subjects', $profesor_id, "API asignó materias/grupos al profe {$profesor_id}");

            DB::commit();

            return response()->json([
                'message' => 'Asignación exitosa.',
                'created' => $creados,
                'teacher_hours' => (int)$total,
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            Log::error('ERROR BD al asignar materias (API)', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return response()->json(['message' => 'Error de base de datos al asignar materias'], 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ERROR inesperado al asignar materias (API)', ['msg'=>$e->getMessage()]);
            return response()->json(['message' => 'Error inesperado al asignar materias'], 500);
        }
    }

    /**
     * Eliminar una asignación concreta (pivot).
     * NOTA: dejamos los bloques en schedule_assignments pero liberamos al profesor (teacher_id = NULL)
     * para que el slot quede disponible.
     */
    public function destroy(Request $request, int $profesor_id, int $teacher_subject_id)
    {
        $row = DB::table('teacher_subjects')->where('teacher_subject_id', $teacher_subject_id)->first();
        if (!$row || (int)$row->teacher_id !== (int)$profesor_id) {
            return response()->json(['message' => 'Asignación no encontrada'], 404);
        }

        DB::beginTransaction();
        try {
            // Liberar bloques oficiales asignados a este profe para ese subject+group
            DB::table('schedule_assignments')
                ->where('teacher_id', $profesor_id)
                ->where('subject_id', $row->subject_id)
                ->where('group_id',   $row->group_id)
                ->update([
                    'teacher_id'       => null,
                    'fyh_actualizacion'=> now(),
                ]);

            // Borrar pivot
            DB::table('teacher_subjects')->where('teacher_subject_id', $teacher_subject_id)->delete();

            // Recalcular horas
            $total = DB::table('teacher_subjects AS ts')
                ->join('subjects AS s', 's.subject_id', '=', 'ts.subject_id')
                ->where('ts.teacher_id', $profesor_id)
                ->sum('s.weekly_hours');

            DB::table('teachers')
                ->where('teacher_id', $profesor_id)
                ->update([
                    'hours'             => (int)($total ?? 0),
                    'fyh_actualizacion' => now(),
                ]);

            ActividadGeneral::registrar('ELIMINAR', 'teacher_subjects', $teacher_subject_id, "API eliminó asignación del profe {$profesor_id}");

            DB::commit();
            return response()->json([
                'message' => 'Asignación eliminada.',
                'teacher_hours' => (int)$total,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            Log::error('ERROR BD al eliminar asignación (API)', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return response()->json(['message' => 'Error de base de datos al eliminar la asignación'], 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ERROR inesperado al eliminar asignación (API)', ['msg'=>$e->getMessage()]);
            return response()->json(['message' => 'Error inesperado al eliminar la asignación'], 500);
        }
    }

    /**
     * AJAX (API): materias por grupo → JSON
     * Body: { "group_id": <int> }
     */
    public function materiasPorGrupo(Request $request, int $profesor_id)
    {
        $groupId = (int) $request->input('group_id');
        if (!$groupId) {
            return response()->json(['items' => []]);
        }

        $group = DB::table('groups')->where('group_id', $groupId)->first();
        if (!$group) {
            return response()->json(['items' => []]);
        }

        $materias = DB::table('program_term_subjects AS pts')
            ->join('subjects AS s', 's.subject_id', '=', 'pts.subject_id')
            ->where('pts.program_id', $group->program_id)
            ->where('pts.term_id',    $group->term_id)
            ->select('s.subject_id','s.subject_name','s.weekly_hours')
            ->distinct()
            ->orderBy('s.subject_name')
            ->get();

        return response()->json(['items' => $materias]);
    }

    /**
     * AJAX (API): total de horas actuales del profe
     */
    public function horasProfesor(Request $request, int $profesor_id)
    {
        $total = DB::table('teacher_subjects AS ts')
            ->join('subjects AS s', 's.subject_id', '=', 'ts.subject_id')
            ->where('ts.teacher_id', (int)$profesor_id)
            ->sum('s.weekly_hours');

        return response()->json(['hours' => (int)($total ?? 0)]);
    }

    /* ====================== HELPERS (mismo flujo del web) ====================== */

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

            // evitar duplicado exacto
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
        // Info del grupo: aula asignada y turno
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

        // 3) completar huecos de 1h respetando reglas y evitando duplicar día si hubo manual
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
            if (isset($slots['start'])) {
                $slots = [$slots]; // normaliza
            }

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
