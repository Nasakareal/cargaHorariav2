<?php

namespace App\Http\Controllers\Api\Horarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HorarioManualApiController extends Controller
{
    // ======= LECTURA PARA GRID/CALENDAR (idénticas a web pero JSON) =======

    // Eventos de un grupo (calendar/json)
    public function dataPorGrupo($grupo_id, Request $request)
    {
        $group_id = (int) $grupo_id;
        $lab_id   = $request->query('lab_id'); // opcional

        $q = DB::table('manual_schedule_assignments as a')
            ->join('subjects as m', 'a.subject_id', '=', 'm.subject_id')
            ->select('a.assignment_id','a.subject_id','m.subject_name','a.start_time','a.end_time','a.schedule_day','a.lab1_assigned','a.lab2_assigned')
            ->where('a.group_id', $group_id)
            ->where('a.estado', 'activo');

        if (!is_null($lab_id)) {
            if ((int)$lab_id === 1) $q->where('a.lab1_assigned', 1);
            elseif ((int)$lab_id === 2) $q->where('a.lab2_assigned', 1);
        }

        $rows = $q->orderBy('a.start_time')->get();
        return response()->json($this->buildEvents($rows));
    }

    // Eventos de un profesor (calendar/json)
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

        return response()->json($this->buildEvents($rows));
    }

    // Eventos por aula o laboratorio
    public function eventosPorEspacio(Request $request)
    {
        $aula_id = $request->query('aula_id');
        $lab_id  = $request->query('lab_id');

        $q = DB::table('manual_schedule_assignments as a')
            ->join('subjects as m', 'a.subject_id', '=', 'm.subject_id')
            ->join('groups as g', 'a.group_id', '=', 'g.group_id')
            ->select('a.assignment_id','a.subject_id','m.subject_name','a.start_time','a.end_time','a.schedule_day','a.group_id','g.group_name')
            ->whereNotNull('a.schedule_day')
            ->where('a.estado', 'activo');

        if ($aula_id) {
            $q->where('a.classroom_id', (int)$aula_id);
        } elseif ($lab_id) {
            $q->where(function($w) use ($lab_id) {
                $w->where('a.lab1_assigned', (int)$lab_id)
                  ->orWhere('a.lab2_assigned', (int)$lab_id);
            });
        } else {
            return response()->json([]); // sin filtros no regresamos nada
        }

        return response()->json($q->orderBy('a.start_time')->get());
    }

    // Combos (grupos/materias/labs/aulas)
    public function opciones(Request $request)
    {
        $group_id = $request->query('group_id');

        $data = [
            'grupos'   => DB::table('groups')->select('group_id','group_name','area','classroom_assigned')->where('estado','1')->orderBy('group_name')->get(),
            'labs'     => [],
            'aulas'    => [],
            'materias' => [],
        ];

        if ($group_id) {
            $grupo = DB::table('groups')->select('group_id','area','classroom_assigned')->where('group_id', (int)$group_id)->where('estado','1')->first();
            if ($grupo) {
                $data['labs'] = DB::table('labs as l')
                    ->select('l.lab_id','l.lab_name','l.fyh_creacion','l.description')
                    ->whereRaw('FIND_IN_SET(?, l.area) > 0', [$grupo->area])
                    ->orderBy('l.lab_name')->get();

                if (!is_null($grupo->classroom_assigned)) {
                    $data['aulas'] = DB::table('groups as g')
                        ->join('classrooms as c', 'g.classroom_assigned', '=', 'c.classroom_id')
                        ->select('g.classroom_assigned', DB::raw("CONCAT(c.classroom_name, '(', RIGHT(c.building, 1), ')') AS aula_nombre"))
                        ->where('g.group_id', (int)$group_id)->get();
                }

                $data['materias'] = DB::table('subjects as m')
                    ->join('group_subjects as gs', 'm.subject_id', '=', 'gs.subject_id')
                    ->select('m.subject_id', 'm.subject_name')
                    ->where('gs.group_id', (int)$group_id)
                    ->orderBy('m.subject_name')->get();
            }
        }

        return response()->json($data);
    }

    // ======= ESCRITURA (crear/mover/borrar) =======

    // Crear (drop en slot vacío)
    public function store(Request $request)
    {
        $subject_id    = (int) $request->input('subject_id');
        $start_time    = $this->toHms($request->input('start_time'));
        $end_time      = $request->input('end_time') ? $this->toHms($request->input('end_time')) : null;
        $schedule_day  = $this->canonicalDay($request->input('schedule_day'));
        $group_id      = (int) $request->input('group_id');
        $lab_id        = $request->input('lab_id');
        $aula_id       = $request->input('aula_id');
        $tipo_espacio  = $request->input('tipo_espacio'); // "Laboratorio" | "Aula"

        if (!$subject_id || !$start_time || !$schedule_day || !$group_id) {
            return response()->json(['status'=>'error','message'=>'Faltan datos requeridos.'],422);
        }

        if ($tipo_espacio === 'Laboratorio') {
            if (empty($lab_id)) return response()->json(['status'=>'error','message'=>'Selecciona un laboratorio.'],422);
            $aula_id = null;
        } elseif ($tipo_espacio === 'Aula') {
            if (empty($aula_id)) return response()->json(['status'=>'error','message'=>'Selecciona un aula.'],422);
            $lab_id = null;
        } else {
            return response()->json(['status'=>'error','message'=>'Tipo de espacio inválido.'],422);
        }

        if (!$end_time) $end_time = Carbon::createFromFormat('H:i:s',$start_time)->addHour()->format('H:i:s');

        try {
            DB::beginTransaction();

            $teacher_id = optional(
                DB::table('teacher_subjects')->select('teacher_id')->where('subject_id',$subject_id)->where('group_id',$group_id)->first()
            )->teacher_id;

            // Conflictos ESPACIO (manual y schedule)
            if ($this->conflictoEspacioManual($schedule_day,$start_time,$end_time,$tipo_espacio,$lab_id,$aula_id)) {
                DB::rollBack(); return response()->json(['status'=>'error','message'=>'El espacio seleccionado ya está ocupado.'],409);
            }
            if ($this->conflictoEspacioSchedule($schedule_day,$start_time,$end_time,$tipo_espacio,$lab_id,$aula_id)) {
                DB::rollBack(); return response()->json(['status'=>'error','message'=>'El espacio está ocupado (schedule_assignments).'],409);
            }

            // Conflictos PROFESOR
            if ($teacher_id) {
                if ($this->conflictoTeacherManual($teacher_id,$schedule_day,$start_time,$end_time)) {
                    DB::rollBack(); return response()->json(['status'=>'error','message'=>'El profesor ya está ocupado.'],409);
                }
                if ($this->conflictoTeacherSchedule($teacher_id,$schedule_day,$start_time,$end_time)) {
                    DB::rollBack(); return response()->json(['status'=>'error','message'=>'El profesor ya está ocupado (schedule).'],409);
                }
            }

            // Conflictos GRUPO
            if ($this->conflictoGroupManual($group_id,$schedule_day,$start_time,$end_time)) {
                DB::rollBack(); return response()->json(['status'=>'error','message'=>'El grupo ya está ocupado.'],409);
            }
            if ($this->conflictoGroupSchedule($group_id,$schedule_day,$start_time,$end_time)) {
                DB::rollBack(); return response()->json(['status'=>'error','message'=>'El grupo ya está ocupado (schedule).'],409);
            }

            // Horas semanales
            $subject = DB::table('subjects')->select('weekly_hours')->where('subject_id',$subject_id)->first();
            if (!$subject) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'La materia no existe.'],404); }
            $weekly_hours = (float)$subject->weekly_hours;
            $duration     = $this->hoursDiff($start_time,$end_time);

            $sumManual   = $this->sumaHoras('manual_schedule_assignments',$subject_id,$group_id);
            $sumSchedule = $this->sumaHoras('schedule_assignments',$subject_id,$group_id);
            if ($sumManual + $duration > $weekly_hours || $sumSchedule + $duration > $weekly_hours) {
                DB::rollBack(); return response()->json(['status'=>'error','message'=>'Excede horas semanales.'],409);
            }

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
                'classroom_id' => $tipo_espacio==='Aula' ? (int)$aula_id : null,
                'lab1_assigned'=> $tipo_espacio==='Laboratorio' ? (int)$lab_id : null,
            ];
            $assignment_id = DB::table('manual_schedule_assignments')->insertGetId($dataManual);

            // Insert espejo en schedule_assignments (mismo assignment_id)
            $dataSch = [
                'assignment_id' => $assignment_id,
                'subject_id'    => $subject_id,
                'teacher_id'    => $teacher_id,
                'group_id'      => $group_id,
                'start_time'    => $start_time,
                'end_time'      => $end_time,
                'schedule_day'  => $schedule_day,
                'fyh_creacion'  => $now,
                'tipo_espacio'  => $tipo_espacio,
                'estado'        => 'activo',
                'classroom_id'  => $tipo_espacio==='Aula' ? (int)$aula_id : null,
                'lab_id'        => $tipo_espacio==='Laboratorio' ? (int)$lab_id : null,
            ];
            DB::table('schedule_assignments')->insert($dataSch);

            DB::commit();
            return response()->json(['status'=>'success','assignment_id'=>$assignment_id]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("API store manual: ".$e->getMessage());
            return response()->json(['status'=>'error','message'=>$e->getMessage()],500);
        }
    }

    // Mover/editar (drag & drop)
    public function update(Request $request, $id)
    {
        $assignment_id = (int)$id;

        $subject_id    = (int) $request->input('subject_id');
        $start_time    = $this->toHms($request->input('start_time'));
        $end_time      = $request->input('end_time') ? $this->toHms($request->input('end_time')) : null;
        $schedule_day  = $this->canonicalDay($request->input('schedule_day'));
        $group_id      = (int) $request->input('group_id');
        $lab_id        = $request->input('lab_id');
        $aula_id       = $request->input('aula_id');
        $tipo_espacio  = $request->input('tipo_espacio');

        if (!$subject_id || !$start_time || !$schedule_day || !$group_id) {
            return response()->json(['status'=>'error','message'=>'Faltan datos requeridos.'],422);
        }
        if (!$end_time) $end_time = Carbon::createFromFormat('H:i:s',$start_time)->addHour()->format('H:i:s');

        if ($tipo_espacio === 'Laboratorio') { if (empty($lab_id)) return response()->json(['status'=>'error','message'=>'Selecciona laboratorio.'],422); $aula_id=null; }
        elseif ($tipo_espacio === 'Aula')    { if (empty($aula_id)) return response()->json(['status'=>'error','message'=>'Selecciona aula.'],422); $lab_id=null; }
        else return response()->json(['status'=>'error','message'=>'Tipo de espacio inválido.'],422);

        try {
            DB::beginTransaction();

            $teacher_id = optional(
                DB::table('teacher_subjects')->select('teacher_id')->where('subject_id',$subject_id)->where('group_id',$group_id)->first()
            )->teacher_id;

            // Conflictos (excluyendo este id)
            if ($this->conflictoEspacioManual($schedule_day,$start_time,$end_time,$tipo_espacio,$lab_id,$aula_id,$assignment_id)) {
                DB::rollBack(); return response()->json(['status'=>'error','message'=>'El espacio está ocupado.'],409);
            }
            if ($this->conflictoEspacioSchedule($schedule_day,$start_time,$end_time,$tipo_espacio,$lab_id,$aula_id,$assignment_id)) {
                DB::rollBack(); return response()->json(['status'=>'error','message'=>'El espacio está ocupado (schedule).'],409);
            }
            if ($teacher_id) {
                if ($this->conflictoTeacherManual($teacher_id,$schedule_day,$start_time,$end_time,$assignment_id)) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El profesor ya está ocupado.'],409); }
                if ($this->conflictoTeacherSchedule($teacher_id,$schedule_day,$start_time,$end_time,$assignment_id)) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El profesor ya está ocupado (schedule).'],409); }
            }
            if ($this->conflictoGroupManual($group_id,$schedule_day,$start_time,$end_time,$assignment_id)) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El grupo ya está ocupado.'],409); }
            if ($this->conflictoGroupSchedule($group_id,$schedule_day,$start_time,$end_time,$assignment_id)) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'El grupo ya está ocupado (schedule).'],409); }

            // Horas semanales (excluyendo este id)
            $subject = DB::table('subjects')->select('weekly_hours')->where('subject_id',$subject_id)->first();
            if (!$subject) { DB::rollBack(); return response()->json(['status'=>'error','message'=>'La materia no existe.'],404); }
            $weekly_hours = (float)$subject->weekly_hours;
            $duration     = $this->hoursDiff($start_time,$end_time);

            $sumManual   = $this->sumaHoras('manual_schedule_assignments',$subject_id,$group_id,$assignment_id);
            $sumSchedule = $this->sumaHoras('schedule_assignments',$subject_id,$group_id,$assignment_id);
            if ($sumManual + $duration > $weekly_hours || $sumSchedule + $duration > $weekly_hours) {
                DB::rollBack(); return response()->json(['status'=>'error','message'=>'Excede horas semanales.'],409);
            }

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
                'classroom_id'      => $tipo_espacio==='Aula' ? (int)$aula_id : null,
                'lab1_assigned'     => $tipo_espacio==='Laboratorio' ? (int)$lab_id : null,
            ];
            DB::table('manual_schedule_assignments')->where('assignment_id',$assignment_id)->update($dataManual);

            // Update espejo
            $dataSch = [
                'subject_id'        => $subject_id,
                'teacher_id'        => $teacher_id,
                'start_time'        => $start_time,
                'end_time'          => $end_time,
                'schedule_day'      => $schedule_day,
                'fyh_actualizacion' => $now,
                'estado'            => 'activo',
                'tipo_espacio'      => $tipo_espacio,
                'classroom_id'      => $tipo_espacio==='Aula' ? (int)$aula_id : null,
                'lab_id'            => $tipo_espacio==='Laboratorio' ? (int)$lab_id : null,
            ];
            DB::table('schedule_assignments')->where('assignment_id',$assignment_id)->update($dataSch);

            DB::commit();
            return response()->json(['status'=>'success']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("API update manual: ".$e->getMessage());
            return response()->json(['status'=>'error','message'=>$e->getMessage()],500);
        }
    }

    // Borrar (click en evento)
    public function destroy($id)
    {
        $assignment_id = (int)$id;
        try {
            DB::beginTransaction();

            $now = Carbon::now()->format('Y-m-d H:i:s');
            DB::table('manual_schedule_assignments')->where('assignment_id',$assignment_id)->update(['estado'=>'inactivo','fyh_actualizacion'=>$now]);
            DB::table('schedule_assignments')->where('assignment_id',$assignment_id)->update(['estado'=>'inactivo','fyh_actualizacion'=>$now]);

            DB::commit();
            return response()->json(['status'=>'success']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status'=>'error','message'=>$e->getMessage()],500);
        }
    }

    // Aliases para la UI de drag&drop (compatibles con tus rutas AJAX)
    public function mover(Request $request)  { return $this->update($request, (int)$request->input('assignment_id')); }
    public function borrar(Request $request) { return $this->destroy((int)$request->input('assignment_id')); }
    public function crear(Request $request)  { return $this->store($request); }

    // ======= HELPERS =======

    private function toHms(string $time): string
    {
        return preg_match('/^\d{2}:\d{2}$/',$time) ? $time.':00' : $time;
    }

    private function hoursDiff(string $start, string $end): float
    {
        [$sh,$sm,$ss] = array_map('intval', explode(':',$start));
        [$eh,$em,$es] = array_map('intval', explode(':',$end));
        $diff = max(0, ($eh*3600+$em*60+$es) - ($sh*3600+$sm*60+$ss));
        return $diff/3600.0;
    }

    private function buildEvents($rows): array
    {
        $map = ['lunes'=>1,'martes'=>2,'miércoles'=>3,'miercoles'=>3,'jueves'=>4,'viernes'=>5,'sábado'=>6,'sabado'=>6,'domingo'=>0];
        $events=[]; $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        foreach ($rows as $a) {
            $key = mb_strtolower($a->schedule_day,'UTF-8'); if (!isset($map[$key])) continue;
            $base = $monday->copy()->addDays(($map[$key]+6)%7);
            $start = Carbon::parse($base->format('Y-m-d').' '.$this->toHms($a->start_time));
            $end   = Carbon::parse($base->format('Y-m-d').' '.$this->toHms($a->end_time));
            $events[] = [
                'title'         => (string)$a->subject_name,
                'start'         => $start->format('Y-m-d\TH:i:s'),
                'end'           => $end->format('Y-m-d\TH:i:s'),
                'subject_id'    => (int)$a->subject_id,
                'assignment_id' => (int)$a->assignment_id,
            ];
        }
        return $events;
    }

    private function canonicalDay(string $d): string
    {
        $k = mb_strtolower(trim($d),'UTF-8');
        $map = ['lunes'=>'Lunes','martes'=>'Martes','miercoles'=>'Miércoles','miércoles'=>'Miércoles','jueves'=>'Jueves','viernes'=>'Viernes','sabado'=>'Sábado','sábado'=>'Sábado','domingo'=>'Domingo'];
        return $map[$k] ?? 'Lunes';
    }

    private function sumaHoras(string $table,int $subject_id,int $group_id, ?int $excludeId=null): float
    {
        $q = DB::table($table)->select('start_time','end_time')
            ->where('subject_id',$subject_id)->where('group_id',$group_id)->where('estado','activo');
        if ($excludeId) $q->where('assignment_id','!=',$excludeId);
        $total=0.0; foreach ($q->get() as $r) { $total += $this->hoursDiff($r->start_time,$r->end_time); }
        return $total;
    }

    // Conflictos (reutilizables)
    private function conflictoEspacioManual($day,$start,$end,$tipo,$lab_id,$aula_id,$excludeId=null): bool
    {
        $q = DB::table('manual_schedule_assignments')
            ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
            ->whereRaw('? < end_time AND ? > start_time',[$start,$end]);
        if ($tipo==='Laboratorio') $q->where(function($qq) use($lab_id){ $qq->where('lab1_assigned',(int)$lab_id)->orWhere('lab2_assigned',(int)$lab_id); });
        else $q->where('classroom_id',(int)$aula_id);
        if ($excludeId) $q->where('assignment_id','!=',$excludeId);
        return $q->exists();
    }

    private function conflictoEspacioSchedule($day,$start,$end,$tipo,$lab_id,$aula_id,$excludeId=null): bool
    {
        $q = DB::table('schedule_assignments')
            ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
            ->whereRaw('? < end_time AND ? > start_time',[$start,$end]);
        if ($tipo==='Laboratorio') $q->where('lab_id',(int)$lab_id);
        else $q->where('classroom_id',(int)$aula_id);
        if ($excludeId) $q->where('assignment_id','!=',$excludeId);
        return $q->exists();
    }

    private function conflictoTeacherManual($teacher_id,$day,$start,$end,$excludeId=null): bool
    {
        $q = DB::table('manual_schedule_assignments')
            ->where('teacher_id',$teacher_id)->where('estado','activo')
            ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
            ->whereRaw('? < end_time AND ? > start_time',[$start,$end]);
        if ($excludeId) $q->where('assignment_id','!=',$excludeId);
        return $q->exists();
    }

    private function conflictoTeacherSchedule($teacher_id,$day,$start,$end,$excludeId=null): bool
    {
        $q = DB::table('schedule_assignments')
            ->where('teacher_id',$teacher_id)->where('estado','activo')
            ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
            ->whereRaw('? < end_time AND ? > start_time',[$start,$end]);
        if ($excludeId) $q->where('assignment_id','!=',$excludeId);
        return $q->exists();
    }

    private function conflictoGroupManual($group_id,$day,$start,$end,$excludeId=null): bool
    {
        $q = DB::table('manual_schedule_assignments')
            ->where('group_id',$group_id)->where('estado','activo')
            ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
            ->whereRaw('? < end_time AND ? > start_time',[$start,$end]);
        if ($excludeId) $q->where('assignment_id','!=',$excludeId);
        return $q->exists();
    }

    private function conflictoGroupSchedule($group_id,$day,$start,$end,$excludeId=null): bool
    {
        $q = DB::table('schedule_assignments')
            ->where('group_id',$group_id)->where('estado','activo')
            ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
            ->whereRaw('? < end_time AND ? > start_time',[$start,$end]);
        if ($excludeId) $q->where('assignment_id','!=',$excludeId);
        return $q->exists();
    }
}
