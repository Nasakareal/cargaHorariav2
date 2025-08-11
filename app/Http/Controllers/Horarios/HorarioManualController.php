<?php

namespace App\Http\Controllers\Horarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HorarioManualController extends Controller
{
    // ============================
    // 1) Blade principal
    // ============================
    public function index(Request $request)
    {
        // Filtros opcionales para precargar combos (grupo, lab, etc.)
        $group_id = $request->query('group_id');
        $lab_id   = $request->query('lab_id');

        // Grupos activos
        $grupos = DB::table('groups')
            ->select('group_id', 'group_name', 'area', 'classroom_assigned')
            ->where('estado', '1') // se mantiene '1' porque así lo usabas en PHP
            ->orderBy('group_name')
            ->get();

        // Si viene group_id, obtener área del grupo y labs que aplican (FIND_IN_SET)
        $labs = collect();
        $aulas = collect();
        $materias = collect();

        if ($group_id) {
            $grupo = DB::table('groups')
                ->select('group_id', 'area', 'classroom_assigned')
                ->where('group_id', $group_id)
                ->where('estado', '1')
                ->first();

            if ($grupo) {
                $group_area = $grupo->area;

                // Labs que incluyen el área del grupo (area es CSV en labs.area)
                $labs = DB::table('labs as l')
                    ->select('l.lab_id', 'l.lab_name', 'l.fyh_creacion', 'l.description')
                    ->whereRaw('FIND_IN_SET(?, l.area) > 0', [$group_area])
                    ->orderBy('l.lab_name')
                    ->get();

                // Aula asignada (como en tu PHP)
                if (!is_null($grupo->classroom_assigned)) {
                    $aulas = DB::table('groups as g')
                        ->join('classrooms as c', 'g.classroom_assigned', '=', 'c.classroom_id')
                        ->select('g.classroom_assigned', DB::raw("CONCAT(c.classroom_name, '(', RIGHT(c.building, 1), ')') AS aula_nombre"))
                        ->where('g.group_id', $group_id)
                        ->get();
                }

                // Materias del grupo
                $materias = DB::table('subjects as m')
                    ->join('group_subjects as gs', 'm.subject_id', '=', 'gs.subject_id')
                    ->select('m.subject_id', 'm.subject_name')
                    ->where('gs.group_id', $group_id)
                    ->orderBy('m.subject_name')
                    ->get();
            }
        }

        // Renderiza tu blade (ajusta la ruta si usas otra carpeta)
        return view('horarios.manual.index', compact(
            'grupos', 'group_id', 'labs', 'lab_id', 'aulas', 'materias'
        ));
    }

    // ============================
    // 2) Crear asignación (drop en slot vacío)
    // ============================
    public function store(Request $request)
    {
        $subject_id    = (int) $request->input('subject_id');
        $start_time    = $request->input('start_time');
        $end_time      = $request->input('end_time');
        $schedule_day  = $this->canonicalDay($request->input('schedule_day')); // <<<<<<
        $group_id      = (int) $request->input('group_id');
        $lab_id        = $request->input('lab_id');
        $aula_id       = $request->input('aula_id');
        $tipo_espacio  = $request->input('tipo_espacio'); // "Laboratorio" | "Aula"

        if (empty($subject_id) || empty($start_time) || empty($schedule_day) || empty($group_id)) {
            return response()->json(['status' => 'error', 'message' => 'Faltan datos requeridos.'], 422);
        }

        if ($tipo_espacio === 'Laboratorio') {
            if (empty($lab_id) || (int)$lab_id === 0) {
                return response()->json(['status' => 'error', 'message' => 'Debe seleccionar un laboratorio para asignar.'], 422);
            }
            $aula_id = null;
        } elseif ($tipo_espacio === 'Aula') {
            if (empty($aula_id) || (int)$aula_id === 0) {
                return response()->json(['status' => 'error', 'message' => 'Debe seleccionar un aula para asignar.'], 422);
            }
            $lab_id = null;
        } else {
            return response()->json(['status' => 'error', 'message' => 'Tipo de espacio inválido.'], 422);
        }

        if (empty($end_time)) {
            $end_time = Carbon::createFromFormat('H:i:s', $this->toHms($start_time))->addHour()->format('H:i:s');
        }

        $start_time = $this->toHms($start_time);
        $end_time   = $this->toHms($end_time);

        try {
            DB::beginTransaction();

            $teacher = DB::table('teacher_subjects as ts')
                ->select('ts.teacher_id')
                ->where('ts.subject_id', $subject_id)
                ->where('ts.group_id', $group_id)
                ->first();
            $teacher_id = $teacher ? (int)$teacher->teacher_id : null;

            // Conflicto de ESPACIO en manual (lab1 OR lab2) / case-insensitive del día
            if ($tipo_espacio === 'Laboratorio') {
                $conflictManual = DB::table('manual_schedule_assignments')
                    ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                    ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                    ->where(function($q) use ($lab_id){
                        $q->where('lab1_assigned', (int)$lab_id)
                          ->orWhere('lab2_assigned', (int)$lab_id);
                    })
                    ->exists();
            } else {
                $conflictManual = DB::table('manual_schedule_assignments')
                    ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                    ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                    ->where('classroom_id', (int)$aula_id)
                    ->exists();
            }
            if ($conflictManual) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El espacio seleccionado ya está ocupado en ese horario.'], 409); }

            // Conflicto de ESPACIO en schedule
            if ($tipo_espacio === 'Laboratorio') {
                $conflictSchedule = DB::table('schedule_assignments')
                    ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                    ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                    ->where('lab_id', (int)$lab_id)
                    ->exists();
            } else {
                $conflictSchedule = DB::table('schedule_assignments')
                    ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                    ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                    ->where('classroom_id', (int)$aula_id)
                    ->exists();
            }
            if ($conflictSchedule) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El espacio está ocupado (schedule_assignments).'], 409); }

            // Conflicto de PROFESOR
            if (!is_null($teacher_id)) {
                $conflictTeacherManual = DB::table('manual_schedule_assignments')
                    ->where('teacher_id', $teacher_id)
                    ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                    ->where('estado', 'activo')
                    ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                    ->exists();
                if ($conflictTeacherManual) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El profesor ya está ocupado en ese horario.'], 409); }

                $conflictTeacherSchedule = DB::table('schedule_assignments')
                    ->where('teacher_id', $teacher_id)
                    ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                    ->where('estado', 'activo')
                    ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                    ->exists();
                if ($conflictTeacherSchedule) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El profesor ya está ocupado (schedule_assignments).'], 409); }
            }

            // Conflicto de GRUPO (lo que te faltaba)
            $conflictGroupManual = DB::table('manual_schedule_assignments')
                ->where('group_id', $group_id)
                ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                ->where('estado', 'activo')
                ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                ->exists();
            if ($conflictGroupManual) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El grupo ya está ocupado en ese horario.'], 409); }

            $conflictGroupSchedule = DB::table('schedule_assignments')
                ->where('group_id', $group_id)
                ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                ->where('estado', 'activo')
                ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                ->exists();
            if ($conflictGroupSchedule) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El grupo ya está ocupado (schedule_assignments).'], 409); }

            // Horas semanales
            $subject = DB::table('subjects')->select('weekly_hours')->where('subject_id', $subject_id)->first();
            if (!$subject) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'La materia no existe.'], 404); }

            $weekly_hours   = (float) $subject->weekly_hours;
            $duration_hours = $this->hoursDiff($start_time, $end_time);

            $rowsManual = DB::table('manual_schedule_assignments')
                ->select('start_time', 'end_time')
                ->where('subject_id', $subject_id)->where('group_id', $group_id)->where('estado', 'activo')->get();
            $totalManual = 0.0; foreach ($rowsManual as $r) { $totalManual += $this->hoursDiff($r->start_time, $r->end_time); }
            if ($totalManual + $duration_hours > $weekly_hours) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'Excede horas semanales (manual).'], 409); }

            $rowsSchedule = DB::table('schedule_assignments')
                ->select('start_time', 'end_time')
                ->where('subject_id', $subject_id)->where('group_id', $group_id)->where('estado', 'activo')->get();
            $totalSchedule = 0.0; foreach ($rowsSchedule as $r) { $totalSchedule += $this->hoursDiff($r->start_time, $r->end_time); }
            if ($totalSchedule + $duration_hours > $weekly_hours) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'Excede horas semanales (schedule).'], 409); }

            // Insert manual
            $now = Carbon::now()->format('Y-m-d H:i:s');
            $dataManual = [
                'subject_id'   => $subject_id,
                'teacher_id'   => $teacher_id,
                'group_id'     => $group_id,
                'start_time'   => $start_time,
                'end_time'     => $end_time,
                'schedule_day' => $schedule_day,
                'fyh_creacion' => $now,
                'tipo_espacio' => $tipo_espacio,
                'estado'       => 'activo',
            ];
            if ($tipo_espacio === 'Laboratorio') { $dataManual['lab1_assigned'] = (int)$lab_id; $dataManual['classroom_id'] = null; }
            else { $dataManual['classroom_id'] = (int)$aula_id; $dataManual['lab1_assigned'] = null; }

            $assignment_id = DB::table('manual_schedule_assignments')->insertGetId($dataManual);

            // Insert ESPEJO en schedule con el MISMO assignment_id  <<<<<< CLAVE
            $dataSchedule = [
                'assignment_id' => $assignment_id, // <<<<<<
                'subject_id'    => $subject_id,
                'teacher_id'    => $teacher_id,
                'group_id'      => $group_id,
                'start_time'    => $start_time,
                'end_time'      => $end_time,
                'schedule_day'  => $schedule_day,
                'fyh_creacion'  => $now,
                'tipo_espacio'  => $tipo_espacio,
                'estado'        => 'activo',
            ];
            if ($tipo_espacio === 'Laboratorio') { $dataSchedule['lab_id'] = (int)$lab_id; $dataSchedule['classroom_id'] = null; }
            else { $dataSchedule['classroom_id'] = (int)$aula_id; $dataSchedule['lab_id'] = null; }

            DB::table('schedule_assignments')->insert($dataSchedule);

            DB::commit();

            return response()->json([
                'status'=>'success','message'=>'Asignación guardada.','assignment_id'=>$assignment_id
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error store manual: ".$e->getMessage());
            return response()->json(['status'=>'error','message'=>'Error al guardar: '.$e->getMessage()],500);
        }
    }

    // ============================
    // 3) Update asignación (drag drop o edición)
    // ============================
    public function update(Request $request, $id)
    {
        $assignment_id = (int) $id;

        $subject_id    = (int) $request->input('subject_id');
        $start_time    = $this->toHms($request->input('start_time'));
        $end_time      = $request->input('end_time') ? $this->toHms($request->input('end_time')) : null;
        $schedule_day  = $this->canonicalDay($request->input('schedule_day')); // <<<<<<
        $group_id      = (int) $request->input('group_id');
        $lab_id        = $request->input('lab_id');
        $aula_id       = $request->input('aula_id');
        $tipo_espacio  = $request->input('tipo_espacio');

        if (empty($subject_id) || empty($start_time) || empty($schedule_day) || empty($group_id)) {
            return response()->json(['status' => 'error', 'message' => 'Faltan datos requeridos.'], 422);
        }

        if (!$end_time) { $end_time = Carbon::createFromFormat('H:i:s', $start_time)->addHour()->format('H:i:s'); }

        if ($tipo_espacio === 'Laboratorio') {
            if (empty($lab_id) || (int)$lab_id === 0) return response()->json(['status' => 'error', 'message' => 'Debe seleccionar un laboratorio para asignar.'], 422);
            $aula_id = null;
        } elseif ($tipo_espacio === 'Aula') {
            if (empty($aula_id) || (int)$aula_id === 0) return response()->json(['status' => 'error', 'message' => 'Debe seleccionar un aula para asignar.'], 422);
            $lab_id = null;
        } else return response()->json(['status' => 'error', 'message' => 'Tipo de espacio inválido.'], 422);

        try {
            DB::beginTransaction();

            $teacher = DB::table('teacher_subjects as ts')
                ->select('ts.teacher_id')
                ->where('ts.subject_id', $subject_id)
                ->where('ts.group_id', $group_id)
                ->first();
            $teacher_id = $teacher ? (int)$teacher->teacher_id : null;

            // Conflicto ESPACIO en manual (excluyendo este id)
            if ($tipo_espacio === 'Laboratorio') {
                $conflictManual = DB::table('manual_schedule_assignments')
                    ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                    ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                    ->where(function($q) use ($lab_id){
                        $q->where('lab1_assigned', (int)$lab_id)
                          ->orWhere('lab2_assigned', (int)$lab_id);
                    })
                    ->where('assignment_id', '!=', $assignment_id)
                    ->exists();
            } else {
                $conflictManual = DB::table('manual_schedule_assignments')
                    ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                    ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                    ->where('classroom_id', (int)$aula_id)
                    ->where('assignment_id', '!=', $assignment_id)
                    ->exists();
            }
            if ($conflictManual) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El espacio está ocupado en ese horario.'],409); }

            // Conflicto ESPACIO en schedule (excluyendo este id)
            if ($tipo_espacio === 'Laboratorio') {
                $conflictSchedule = DB::table('schedule_assignments')
                    ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                    ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                    ->where('lab_id', (int)$lab_id)
                    ->where('assignment_id', '!=', $assignment_id)
                    ->exists();
            } else {
                $conflictSchedule = DB::table('schedule_assignments')
                    ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                    ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                    ->where('classroom_id', (int)$aula_id)
                    ->where('assignment_id', '!=', $assignment_id)
                    ->exists();
            }
            if ($conflictSchedule) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El espacio está ocupado (schedule_assignments).'],409); }

            // Conflicto PROFESOR (excluyendo este id)
            if (!is_null($teacher_id)) {
                $conflictTeacherManual = DB::table('manual_schedule_assignments')
                    ->where('teacher_id', $teacher_id)
                    ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                    ->where('estado', 'activo')
                    ->where('assignment_id', '!=', $assignment_id)
                    ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                    ->exists();
                if ($conflictTeacherManual) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El profesor ya está ocupado en ese horario.'],409); }

                $conflictTeacherSchedule = DB::table('schedule_assignments')
                    ->where('teacher_id', $teacher_id)
                    ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                    ->where('estado', 'activo')
                    ->where('assignment_id', '!=', $assignment_id)
                    ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                    ->exists();
                if ($conflictTeacherSchedule) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El profesor ya está ocupado (schedule_assignments).'],409); }
            }

            // Conflicto GRUPO (excluyendo este id)
            $conflictGroupManual = DB::table('manual_schedule_assignments')
                ->where('group_id', $group_id)
                ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                ->where('estado', 'activo')
                ->where('assignment_id', '!=', $assignment_id)
                ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                ->exists();
            if ($conflictGroupManual) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El grupo ya está ocupado en ese horario.'],409); }

            $conflictGroupSchedule = DB::table('schedule_assignments')
                ->where('group_id', $group_id)
                ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$schedule_day])
                ->where('estado', 'activo')
                ->where('assignment_id', '!=', $assignment_id)
                ->whereRaw('? < end_time AND ? > start_time', [$start_time, $end_time])
                ->exists();
            if ($conflictGroupSchedule) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El grupo ya está ocupado (schedule_assignments).'],409); }

            // Horas semanales
            $subject = DB::table('subjects')->select('weekly_hours')->where('subject_id', $subject_id)->first();
            if (!$subject) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'La materia no existe.'],404); }
            $weekly_hours = (float)$subject->weekly_hours;
            $duration_hours = $this->hoursDiff($start_time, $end_time);

            $rowsManual = DB::table('manual_schedule_assignments')
                ->select('start_time','end_time')
                ->where('subject_id',$subject_id)->where('group_id',$group_id)->where('estado','activo')
                ->where('assignment_id','!=',$assignment_id)->get();
            $totalManual = 0.0; foreach ($rowsManual as $r) { $totalManual += $this->hoursDiff($r->start_time,$r->end_time); }
            if ($totalManual + $duration_hours > $weekly_hours) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'Excede horas semanales (manual).'],409); }

            $rowsSchedule = DB::table('schedule_assignments')
                ->select('start_time','end_time')
                ->where('subject_id',$subject_id)->where('group_id',$group_id)->where('estado','activo')
                ->where('assignment_id','!=',$assignment_id)->get();
            $totalSchedule = 0.0; foreach ($rowsSchedule as $r) { $totalSchedule += $this->hoursDiff($r->start_time,$r->end_time); }
            if ($totalSchedule + $duration_hours > $weekly_hours) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'Excede horas semanales (schedule).'],409); }

            // Update manual
            $now = Carbon::now()->format('Y-m-d H:i:s');
            $dataManual = [
                'subject_id'        => $subject_id,
                'teacher_id'        => $teacher_id,
                'start_time'        => $start_time,
                'end_time'          => $end_time,
                'schedule_day'      => $schedule_day,
                'fyh_actualizacion' => $now,
                'estado'            => 'activo',
                'tipo_espacio'      => $tipo_espacio,
            ];
            if ($tipo_espacio === 'Laboratorio') { $dataManual['lab1_assigned'] = (int)$lab_id; $dataManual['classroom_id'] = null; }
            else { $dataManual['classroom_id'] = (int)$aula_id; $dataManual['lab1_assigned'] = null; }

            DB::table('manual_schedule_assignments')->where('assignment_id', $assignment_id)->update($dataManual);

            // Update espejo
            $dataSchedule = [
                'subject_id'        => $subject_id,
                'teacher_id'        => $teacher_id,
                'start_time'        => $start_time,
                'end_time'          => $end_time,
                'schedule_day'      => $schedule_day,
                'fyh_actualizacion' => $now,
                'estado'            => 'activo',
                'tipo_espacio'      => $tipo_espacio,
            ];
            if ($tipo_espacio === 'Laboratorio') { $dataSchedule['lab_id'] = (int)$lab_id; $dataSchedule['classroom_id'] = null; }
            else { $dataSchedule['classroom_id'] = (int)$aula_id; $dataSchedule['lab_id'] = null; }

            DB::table('schedule_assignments')->where('assignment_id', $assignment_id)->update($dataSchedule);

            DB::commit();
            return response()->json(['status'=>'success','message'=>'Asignación actualizada.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error update manual: ".$e->getMessage());
            return response()->json(['status'=>'error','message'=>'Error al actualizar: '.$e->getMessage()],500);
        }
    }


    // ============================
    // 4) Eliminar (click borrar) - endpoint REST
    // ============================
    public function destroy($id)
    {
        $assignment_id = (int) $id;
        try {
            DB::beginTransaction();

            // En vez de DELETE físico, marcar inactivo (coincide con tus consultas por 'activo')
            DB::table('manual_schedule_assignments')
                ->where('assignment_id', $assignment_id)
                ->update(['estado' => 'inactivo', 'fyh_actualizacion' => Carbon::now()->format('Y-m-d H:i:s')]);

            DB::table('schedule_assignments')
                ->where('assignment_id', $assignment_id)
                ->update(['estado' => 'inactivo', 'fyh_actualizacion' => Carbon::now()->format('Y-m-d H:i:s')]);

            DB::commit();
            return response()->json(['status'=>'success','message'=>'Asignación eliminada.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status'=>'error','message'=>'Error al eliminar: '.$e->getMessage()],500);
        }
    }

    // ============================
    // 5) AJAX: mover (drag & drop sobre evento existente)
    // ============================
    public function mover(Request $request)
    {
        // Reutiliza update() con los mismos checks
        $request->merge(['tipo_espacio' => $request->input('tipo_espacio')]); // Laboratorio | Aula
        return $this->update($request, (int)$request->input('assignment_id'));
    }

    // ============================
    // 6) AJAX: borrar (click en evento)
    // ============================
    public function borrar(Request $request)
    {
        return $this->destroy((int)$request->input('assignment_id'));
    }

    // ============================
    // 7) AJAX: crear (drop en slot vacío)
    // ============================
    public function crear(Request $request)
    {
        return $this->store($request);
    }

    // ============================
    // 8) AJAX utilitario: eventos por grupo (calendar/json)
    //     Soporta parámetro opcional lab_id (si quieres filtrar por lab1/lab2 como en tu PHP)
    // ============================
    public function dataPorGrupo($grupo_id, Request $request)
    {
        $group_id = (int) $grupo_id;
        $lab_id   = $request->query('lab_id'); // si mandas 1 => lab1_assigned=1, 2 => lab2_assigned=1 (como tu PHP)

        $q = DB::table('manual_schedule_assignments as a')
            ->join('subjects as m', 'a.subject_id', '=', 'm.subject_id')
            ->select('a.assignment_id','a.subject_id','m.subject_name','a.start_time','a.end_time','a.schedule_day','a.lab1_assigned','a.lab2_assigned')
            ->where('a.group_id', $group_id)
            ->where('a.estado', 'activo');

        if (!is_null($lab_id)) {
            if ((int)$lab_id === 1) {
                $q->where('a.lab1_assigned', 1);
            } elseif ((int)$lab_id === 2) {
                $q->where('a.lab2_assigned', 1);
            }
        }

        $rows = $q->orderBy('a.start_time')->get();
        $events = $this->buildEvents($rows);

        return response()->json($events);
    }

    // ============================
    // 9) AJAX utilitario: eventos por profesor (calendar/json)
    // ============================
    public function dataPorProfesor($profesor_id)
    {
        $teacher_id = (int) $profesor_id;

        $rows = DB::table('manual_schedule_assignments as a')
            ->join('subjects as m', 'a.subject_id', '=', 'm.subject_id')
            ->select('a.assignment_id','a.subject_id','m.subject_name','a.start_time','a.end_time','a.schedule_day')
            ->where('a.teacher_id', $teacher_id)
            ->where('a.estado', 'activo')
            ->orderBy('a.start_time')
            ->get();

        $events = $this->buildEvents($rows);
        return response()->json($events);
    }

    // ============================
    // 10) AJAX utilitario: combos (grupos/materias/labs/aulas, etc.)
    //      Si mandas group_id, regresa labs por área, aula asignada y materias del grupo
    // ============================
    public function opciones(Request $request)
    {
        $group_id = $request->query('group_id');

        $data = [
            'grupos'   => [],
            'labs'     => [],
            'aulas'    => [],
            'materias' => [],
        ];

        // Grupos activos
        $data['grupos'] = DB::table('groups')
            ->select('group_id','group_name','area','classroom_assigned')
            ->where('estado','1')
            ->orderBy('group_name')
            ->get();

        if ($group_id) {
            $grupo = DB::table('groups')
                ->select('group_id','area','classroom_assigned')
                ->where('group_id', (int)$group_id)
                ->where('estado','1')
                ->first();

            if ($grupo) {
                $group_area = $grupo->area;

                $data['labs'] = DB::table('labs as l')
                    ->select('l.lab_id','l.lab_name','l.fyh_creacion','l.description')
                    ->whereRaw('FIND_IN_SET(?, l.area) > 0', [$group_area])
                    ->orderBy('l.lab_name')
                    ->get();

                if (!is_null($grupo->classroom_assigned)) {
                    $data['aulas'] = DB::table('groups as g')
                        ->join('classrooms as c', 'g.classroom_assigned', '=', 'c.classroom_id')
                        ->select('g.classroom_assigned', DB::raw("CONCAT(c.classroom_name, '(', RIGHT(c.building, 1), ')') AS aula_nombre"))
                        ->where('g.group_id', (int)$group_id)
                        ->get();
                }

                $data['materias'] = DB::table('subjects as m')
                    ->join('group_subjects as gs', 'm.subject_id', '=', 'gs.subject_id')
                    ->select('m.subject_id', 'm.subject_name')
                    ->where('gs.group_id', (int)$group_id)
                    ->orderBy('m.subject_name')
                    ->get();
            }
        }

        return response()->json($data);
    }

    // ============================
    // Helpers
    // ============================

    // Convierte "H:i" a "H:i:s" si hace falta
    private function toHms(string $time): string
    {
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time.':00';
        }
        return $time; // se asume ya viene H:i:s
    }

    // Diferencia en horas decimales entre HH:MM:SS
    private function hoursDiff(string $start, string $end): float
    {
        [$sh, $sm, $ss] = array_map('intval', explode(':', $start));
        [$eh, $em, $es] = array_map('intval', explode(':', $end));

        $startSec = $sh * 3600 + $sm * 60 + $ss;
        $endSec   = $eh * 3600 + $em * 60 + $es;

        $diff = max(0, $endSec - $startSec);
        return $diff / 3600.0;
    }

    // Arma eventos para el calendario (semana actual, mapea días en español)
    private function buildEvents($rows): array
    {
        $map = [
            'lunes'      => Carbon::MONDAY,
            'martes'     => Carbon::TUESDAY,
            'miércoles'  => Carbon::WEDNESDAY,
            'miercoles'  => Carbon::WEDNESDAY,
            'jueves'     => Carbon::THURSDAY,
            'viernes'    => Carbon::FRIDAY,
            'sábado'     => Carbon::SATURDAY,
            'sabado'     => Carbon::SATURDAY,
            'domingo'    => Carbon::SUNDAY,
        ];

        $events = [];
        $now = Carbon::now();
        $monday = $now->copy()->startOfWeek(Carbon::MONDAY);

        foreach ($rows as $a) {
            $dayKey = mb_strtolower($a->schedule_day, 'UTF-8');
            if (!isset($map[$dayKey])) {
                // Día inválido, lo saltamos (igual que tu echo en PHP)
                continue;
            }

            $weekday = $map[$dayKey];
            $startDate = $monday->copy()->nextOrSame($weekday);

            $start = Carbon::parse($startDate->format('Y-m-d').' '.$this->toHms($a->start_time));
            $end   = Carbon::parse($startDate->format('Y-m-d').' '.$this->toHms($a->end_time));

            $events[] = [
                'title'         => (string) $a->subject_name,
                'start'         => $start->format('Y-m-d\TH:i:s'),
                'end'           => $end->format('Y-m-d\TH:i:s'),
                'subject_id'    => (int) $a->subject_id,
                'assignment_id' => (int) $a->assignment_id,
                'backgroundColor' => '#FF5733',
                'borderColor'     => '#FF5733',
                'textColor'       => '#fff',
            ];
        }
        return $events;
    }

    // Normaliza el día a como lo guardas en BD (ENUM/Mayúscula con acentos)
    private function canonicalDay(string $d): string
    {
        $k = mb_strtolower(trim($d), 'UTF-8');
        $map = [
            'lunes' => 'Lunes',
            'martes' => 'Martes',
            'miercoles' => 'Miércoles',
            'miércoles' => 'Miércoles',
            'jueves' => 'Jueves',
            'viernes' => 'Viernes',
            'sabado' => 'Sábado',
            'sábado' => 'Sábado',
            'domingo' => 'Domingo',
        ];
        return $map[$k] ?? 'Lunes';
    }

    // ============================
    // AJAX: eventos por aula o laboratorio (como en PHP puro)
    // ============================
    public function eventosPorEspacio(Request $request)
    {
        $aula_id = $request->query('aula_id');
        $lab_id  = $request->query('lab_id');

        $q = DB::table('manual_schedule_assignments as a')
            ->join('subjects as m', 'a.subject_id', '=', 'm.subject_id')
            ->join('groups as g', 'a.group_id', '=', 'g.group_id')
            ->select(
                'a.assignment_id',
                'a.subject_id',
                'm.subject_name',
                'a.start_time',
                'a.end_time',
                'a.schedule_day',
                'a.group_id',
                'g.group_name'
            )
            ->whereNotNull('a.schedule_day')
            ->where('a.estado', 'activo');

        if ($aula_id) {
            $q->where('a.classroom_id', (int) $aula_id);
        } elseif ($lab_id) {
            $q->where(function($w) use ($lab_id) {
                $w->where('a.lab1_assigned', (int) $lab_id)
                  ->orWhere('a.lab2_assigned', (int) $lab_id);
            });
        } else {
            return response()->json([]); // sin filtros no regresamos nada
        }

        return response()->json($q->orderBy('a.start_time')->get());
    }

}
