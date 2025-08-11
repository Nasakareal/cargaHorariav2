<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\ActividadGeneral;
use Illuminate\Support\Facades\DB;

class ProfesorController extends Controller
{


    public function index()
    {
        $profes = DB::table('teachers as t')
            ->leftJoin('teacher_subjects as ts', 'ts.teacher_id', 't.teacher_id')
            ->leftJoin('subjects as s', 's.subject_id', 'ts.subject_id')
            ->leftJoin('program_term_subjects as pts', 'pts.subject_id', 's.subject_id')
            ->leftJoin('programs as ps', 'ps.program_id', 'pts.program_id')
            ->leftJoin('terms as pt', 'pt.term_id', 'pts.term_id')
            ->groupBy('t.teacher_id', 't.teacher_name', 't.clasificacion', 't.hours')
            ->select([
                't.teacher_id',
                't.teacher_name as profesor',
                't.clasificacion',
                't.hours as horas_semanales',
                DB::raw('GROUP_CONCAT(DISTINCT ps.program_name SEPARATOR ", ") AS programas'),
                DB::raw('GROUP_CONCAT(DISTINCT pt.term_name SEPARATOR ", ") AS cuatrimestres'),
                DB::raw('GROUP_CONCAT(s.subject_name SEPARATOR ", ") AS materias'),
            ])
            ->orderBy('t.teacher_name')
            ->get();

        return view('profesores.index', ['profesores' => $profes]);
    }


    public function create()
    {
        return view('profesores.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'teacher_name' => 'required|string|max:255',
        ], [
            'teacher_name.required' => 'El nombre del profesor es obligatorio.',
        ], [
            'teacher_name' => 'nombre del profesor',
        ]);

        try {
            $profesor = Teacher::create([
                'teacher_name'      => $request->teacher_name,
                'estado'            => 'ACTIVO',
                'fyh_creacion'      => now(),
                'fyh_actualizacion' => null,
            ]);

            ActividadGeneral::registrar('CREAR', 'teachers', $profesor->teacher_id, "Creó al profesor {$profesor->teacher_name}");

            return redirect()
                ->route('profesores.index')
                ->with('success', 'Profesor creado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al crear profesor', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return redirect()
                ->route('profesores.index')
                ->with('error', 'Error de base de datos al crear el profesor.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al crear profesor', ['msg'=>$e->getMessage()]);
            return redirect()
                ->route('profesores.index')
                ->with('error', 'Ocurrió un error inesperado al crear el profesor.');
        }
    }

    public function show($id)
    {
        $profesor = Teacher::where('teacher_id', (int)$id)->first();
        if (!$profesor) {
            return redirect()->route('profesores.index')
                ->with('error', 'El profesor no existe o ya fue eliminado.');
        }

        $agg = DB::table('teacher_subjects as ts')
            ->leftJoin('subjects as s', 's.subject_id', '=', 'ts.subject_id')
            ->leftJoin('groups as g',   'g.group_id',   '=', 'ts.group_id')
            ->leftJoin('programs as p', 'p.program_id', '=', 'g.program_id')
            ->where('ts.teacher_id', $profesor->teacher_id)
            ->selectRaw('GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.subject_name SEPARATOR ", ") AS materias')
            ->selectRaw('GROUP_CONCAT(DISTINCT p.program_name ORDER BY p.program_name SEPARATOR ", ") AS programas')
            ->selectRaw('GROUP_CONCAT(DISTINCT g.group_name   ORDER BY g.group_name   SEPARATOR ", ") AS grupos')
            ->selectRaw('COALESCE(SUM(s.weekly_hours),0) AS horas_semanales')
            ->first();

        $horarios = DB::table('teacher_availability')
            ->where('teacher_id', $profesor->teacher_id)
            ->orderByRaw("FIELD(day_of_week,'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo',
                                       'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')")
            ->orderBy('start_time')
            ->get(['day_of_week','start_time','end_time']);

        return view('profesores.show', [
            'profesor'        => $profesor,
            'materias'        => $agg->materias ?? null,
            'programas'       => $agg->programas ?? null,
            'grupos'          => $agg->grupos ?? null,
            'horas_semanales' => (int)($agg->horas_semanales ?? 0),
            'horarios'        => $horarios,
        ]);
    }

    public function edit($id)
    {
        $profesor = Teacher::find($id);
        if (!$profesor) {
            return redirect()->route('profesores.index')
                ->with('error', 'El profesor no existe o ya fue eliminado.');
        }

        // Áreas únicas desde programs (ej. "Ingeniería", "Administración", etc.)
        $areas = DB::table('programs')
            ->whereNotNull('area')
            ->select('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area')
            ->toArray();

        // Programas asignados al profe -> inferimos sus áreas actuales
        $programIds = DB::table('teacher_program_term')
            ->where('teacher_id', $id)
            ->pluck('program_id');

        $areasAsignadas = DB::table('programs')
            ->whereIn('program_id', $programIds)
            ->whereNotNull('area')
            ->select('area')
            ->distinct()
            ->pluck('area')
            ->toArray();

        // Horarios actuales del profe
        $horarios = DB::table('teacher_availability')
            ->where('teacher_id', $id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get(['day_of_week','start_time','end_time']);

        return view('profesores.edit', compact('profesor','areas','areasAsignadas','horarios'));
    }

    public function update(Request $request, $id)
    {
        $profesor = Teacher::find($id);
        if (!$profesor) {
            return redirect()->route('profesores.index')
                ->with('error', 'El profesor no existe o ya fue eliminado.');
        }

        $request->validate([
            'teacher_name' => 'required|string|max:255',
            'clasificacion'=> 'required|in:PTC,PA,TA',
            'areas'        => 'nullable|array',
            'areas.*'      => 'string|max:255',
            'day_of_week'  => 'nullable|array',
            'day_of_week.*'=> 'in:Lunes,Martes,Miércoles,Jueves,Viernes,Sábado,Domingo',
            'start_time'   => 'nullable|array',
            'start_time.*' => 'date_format:H:i',
            'end_time'     => 'nullable|array',
            'end_time.*'   => 'date_format:H:i',
        ], [
            'teacher_name.required' => 'El nombre del profesor es obligatorio.',
            'clasificacion.required'=> 'La clasificación es obligatoria.',
            'clasificacion.in'      => 'Clasificación inválida.',
        ]);

        // Validación extra: pares de horarios y que fin > inicio
        $days  = $request->input('day_of_week', []);
        $starts= $request->input('start_time', []);
        $ends  = $request->input('end_time', []);

        if (count($days) !== count($starts) || count($days) !== count($ends)) {
            return back()->withInput()->with('error','Los horarios enviados son inconsistentes.');
        }
        for ($i=0; $i<count($days); $i++) {
            if (!isset($starts[$i], $ends[$i])) continue;
            if ($starts[$i] >= $ends[$i]) {
                return back()->withInput()->with('error','Cada horario debe tener hora de fin mayor a la de inicio.');
            }
        }

        try {
            DB::beginTransaction();

            // 1) Actualizar datos del profesor
            $profesor->teacher_name      = $request->teacher_name;
            $profesor->clasificacion     = $request->clasificacion;
            $profesor->fyh_actualizacion = now();
            $profesor->save();

            // 2) Rehacer asociaciones profesor-programa según ÁREAS seleccionadas
            DB::table('teacher_program_term')->where('teacher_id', $id)->delete();

            $areasSel = $request->input('areas', []);
            if (!empty($areasSel)) {
                // programas que pertenecen a las áreas seleccionadas
                $programsFromAreas = DB::table('programs')
                    ->whereIn('area', $areasSel)
                    ->pluck('program_id')
                    ->toArray();

                if (!empty($programsFromAreas)) {
                    $rows = [];
                    $now = now();
                    foreach ($programsFromAreas as $pid) {
                        $rows[] = [
                            'teacher_id'   => $id,
                            'program_id'   => $pid,
                            'term_id'      => null,
                            'fyh_creacion' => $now,
                            'estado'       => 'ACTIVO',
                        ];
                    }
                    DB::table('teacher_program_term')->insert($rows);
                }
            }

            // 3) Rehacer disponibilidad (horarios)
            DB::table('teacher_availability')->where('teacher_id', $id)->delete();
            if (!empty($days)) {
                $rows = [];
                $now = now();
                for ($i=0; $i<count($days); $i++) {
                    $rows[] = [
                        'teacher_id'   => $id,
                        'day_of_week'  => $days[$i],
                        'start_time'   => $starts[$i],
                        'end_time'     => $ends[$i],
                        'fyh_creacion' => $now,
                    ];
                }
                DB::table('teacher_availability')->insert($rows);
            }

            // 4) Auditoría
            ActividadGeneral::registrar('ACTUALIZAR', 'teachers', $profesor->teacher_id, "Actualizó al profesor {$profesor->teacher_name}");

            DB::commit();

            return redirect()->route('profesores.index')
                ->with('success', 'Profesor actualizado correctamente.');
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Error BD al actualizar profesor', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Error de base de datos al actualizar el profesor.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error inesperado al actualizar profesor', ['msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Ocurrió un error inesperado al actualizar el profesor.');
        }
    }

    public function destroy($id)
    {
        $profesor = Teacher::withCount('materias')->find($id);
        if (!$profesor) {
            return redirect()->route('profesores.index')
                ->with('error', 'El profesor no existe o ya fue eliminado.');
        }

        if (!Auth::user()->can('eliminar profesores')) {
            return redirect()->route('profesores.index')
                ->with('error', 'No tienes permiso para eliminar profesores.');
        }

        if ($profesor->materias_count > 0) {
            return redirect()->route('profesores.index')
                ->with('error', 'No se puede eliminar un profesor con materias asignadas.');
        }

        try {
            ActividadGeneral::registrar('ELIMINAR', 'teachers', $profesor->teacher_id, "Eliminó al profesor {$profesor->teacher_name}");
            $profesor->delete();

            return redirect()->route('profesores.index')
                ->with('success', 'Profesor eliminado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al eliminar profesor', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return redirect()->route('profesores.index')
                ->with('error', 'Error de base de datos al eliminar el profesor.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al eliminar profesor', ['msg'=>$e->getMessage()]);
            return redirect()->route('profesores.index')
                ->with('error', 'Ocurrió un error inesperado al eliminar el profesor.');
        }
    }

    /* ====== Extra: Asignar Materias ====== */

    /* ====================== VISTA ====================== */
    public function asignarMateriasForm(Request $request, $id)
    {
        $profesor = Teacher::find($id);
        if (!$profesor) {
            return redirect()->route('profesores.index')->with('error', 'El profesor no existe.');
        }

        // Grupos disponibles (ajusta si tienes reglas específicas)
        $grupos = DB::table('groups')
            ->orderBy('group_name')
            ->get(['group_id','group_name','program_id','term_id','turn_id']);

        // Materias ya asignadas al profe (si quieres precargar la caja derecha)
        $materiasAsignadas = DB::table('teacher_subjects AS ts')
            ->join('subjects AS s', 's.subject_id', '=', 'ts.subject_id')
            ->where('ts.teacher_id', $id)
            ->select('s.subject_id','s.subject_name','s.weekly_hours','ts.group_id')
            ->orderBy('s.subject_name')
            ->get();

        return view('profesores.asignar', compact('profesor','grupos','materiasAsignadas'));
    }

    /* ====================== AJAX: materias por grupo ====================== */
    public function materiasPorGrupo(Request $request, $id)
    {
        $groupId = (int) $request->input('group_id');
        if (!$groupId) return response('<!-- group_id inválido -->', 200);

        $group = DB::table('groups')->where('group_id', $groupId)->first();
        if (!$group) return response('<!-- grupo no existe -->', 200);

        $materias = DB::table('program_term_subjects AS pts')
            ->join('subjects AS s', 's.subject_id', '=', 'pts.subject_id')
            ->where('pts.program_id', $group->program_id)
            ->where('pts.term_id',    $group->term_id)
            ->select('s.subject_id','s.subject_name','s.weekly_hours')
            ->distinct() // <- evita duplicados
            // ->groupBy('s.subject_id','s.subject_name','s.weekly_hours') // alternativa si tu MySQL exige group by
            ->orderBy('s.subject_name')
            ->get();

        $html = '';
        foreach ($materias as $m) {
            $html .= '<option value="'.$m->subject_id.'" data-hours="'.$m->weekly_hours.'">'
                   . e($m->subject_name) . '</option>';
        }
        return response($html, 200);
    }


    /* ====================== AJAX: horas actuales del profe ====================== */
    public function horasProfesor(Request $request, $id)
    {
        $total = DB::table('teacher_subjects AS ts')
            ->join('subjects AS s', 's.subject_id', '=', 'ts.subject_id')
            ->where('ts.teacher_id', (int)$id)
            ->sum('s.weekly_hours');

        return response((string) ($total ?? 0), 200);
    }

    /* ====================== GUARDAR ASIGNACIÓN ====================== */
    public function asignarMateriasStore(Request $request, $id)
    {
        $teacherId = (int) $id;

        $request->validate([
            'materias_asignadas' => 'nullable|array',
            'materias_asignadas.*' => 'integer',
            'grupos_asignados'   => 'required|array|min:1',
            'grupos_asignados.*' => 'integer',
        ], [
            'grupos_asignados.required' => 'Seleccione al menos un grupo.',
        ]);

        $materiaIds = $request->input('materias_asignadas', []);
        $grupoIds   = array_filter($request->input('grupos_asignados', []), 'is_numeric');

        if (!$teacherId || empty($grupoIds)) {
            return back()->with('error', 'Faltan datos para asignar.');
        }

        // Carga arrays de turnos/horarios (pon esto en config/horarios.php)
        $horarios_disponibles = config('horarios.disponibles', []);
        $dias_semana          = config('horarios.dias_semana', []);

        DB::beginTransaction();

        try {
            // disponibilidad del profe
            $teacherAvailability = $this->cargarDisponibilidadProfesor($teacherId);

            // horas por materia
            if (!empty($materiaIds)) {
                $rows = DB::table('subjects')
                    ->whereIn('subject_id', $materiaIds)
                    ->select('subject_id','weekly_hours')
                    ->get();
                $mapHours = [];
                foreach ($rows as $r) $mapHours[$r->subject_id] = (int)$r->weekly_hours;
            } else {
                $mapHours = [];
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
                        $teacherId,
                        $m,
                        (int) $g,
                        $wh,
                        $errores,
                        $horarios_disponibles,
                        $dias_semana,
                        $teacherAvailability
                    );

                    if (!$ok) continue;

                    // relación teacher_subjects (si no existe)
                    $exists = DB::table('teacher_subjects')
                        ->where('teacher_id', $teacherId)
                        ->where('subject_id', $m)
                        ->where('group_id', $g)
                        ->exists();

                    if (!$exists) {
                        DB::table('teacher_subjects')->insert([
                            'teacher_id'       => $teacherId,
                            'subject_id'       => $m,
                            'group_id'         => $g,
                            'fyh_creacion'     => now(),
                            'fyh_actualizacion'=> now(),
                        ]);
                    }
                }
            }

            if (!empty($errores)) {
                DB::rollBack();
                return back()->with('error', implode(' | ', $errores));
            }

            // actualizar total de horas del profe
            $total = DB::table('teacher_subjects AS ts')
                ->join('subjects AS s', 's.subject_id', '=', 'ts.subject_id')
                ->where('ts.teacher_id', $teacherId)
                ->sum('s.weekly_hours');

            DB::table('teachers')
                ->where('teacher_id', $teacherId)
                ->update([
                    'hours'             => (int)$total,
                    'fyh_actualizacion' => now(),
                ]);

            // actividad
            ActividadGeneral::registrar('ASIGNAR', 'teacher_subjects', $teacherId, "Asignó materias/grupos al profe {$teacherId}");

            DB::commit();
            return redirect()->route('profesores.index')->with('success', 'Asignación exitosa.');
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Error BD al asignar materias', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return back()->with('error', 'Error de base de datos al asignar materias.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error inesperado al asignar materias', ['msg'=>$e->getMessage()]);
            return back()->with('error', 'Ocurrió un error inesperado al asignar materias.');
        }
    }

    /* ====================== HELPERS (clonan tu PHP) ====================== */

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
                // (start_time < end) AND (end_time > start)
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
        // datos del grupo
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

        // 3) completar huecos 1h respetando reglas
        $diasTurno = $dias_semana[$turno];
        $dc = count($diasTurno);
        $i = 0;
        $ciclosSinHueco = 0;

        while ($weeklyHours > 0) {
            $dia = $diasTurno[$i];

            // evitar duplicar día si hay manual del mismo subject ese día
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

            // Puede venir un solo rango ['start' => ..., 'end' => ...]
            // o múltiples rangos [['start'=>...,'end'=>...], ...]
            $slots = $horarios_disponibles[$turno][$dia];
            if (isset($slots['start'])) {
                $slots = [$slots]; // normaliza a array de slots
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
                        break 2; // sale del for y del foreach de slots
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
