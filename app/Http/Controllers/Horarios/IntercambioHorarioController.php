<?php

namespace App\Http\Controllers\Horarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class IntercambioHorarioController extends Controller
{
    // ============================
    // 1) Blade
    // ============================
    public function index(Request $request)
    {
        $group_id = $request->query('group_id');

        // Lista de grupos (filtro tolerante por estado)
        $grupos = DB::table('groups')
            ->select('group_id','group_name')
            ->when(DB::getSchemaBuilder()->hasColumn('groups','estado'), function($q){
                $q->where(function($w){
                    $w->where('estado', 1)
                      ->orWhere('estado', '1')
                      ->orWhere('estado', 'activo');
                });
            })
            ->orderBy('group_name')
            ->get();

        // Render: solo necesitas $grupos (ya no aulas/labs)
        return view('horarios.intercambio.index', compact('grupos','group_id'));
    }
    
    // ============================
    // 2) Borrar (opcional en esta vista)
    // ============================
    public function borrar(Request $r)
    {
        $aid = (int)$r->input('assignment_id');
        if (!$aid) return response()->json(['status'=>'error','message'=>'ID inválido'], 422);

        try {
            DB::beginTransaction();

            // 1) ¿Está en schedule?
            $s = DB::table('schedule_assignments')->where('assignment_id', $aid)->first();
            if ($s) {
                // delete físico en schedule
                DB::table('schedule_assignments')->where('assignment_id', $aid)->delete();

                // borrar espejo en manual por campos
                $base = DB::table('manual_schedule_assignments')
                    ->where('group_id',   $s->group_id)
                    ->where('subject_id', $s->subject_id)
                    ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$s->schedule_day]);

                if (!is_null($s->classroom_id)) {
                    $base->where('classroom_id', (int)$s->classroom_id);
                } elseif (!is_null($s->lab_id)) {
                    $lab = (int)$s->lab_id;
                    $base->where(function($w) use ($lab){
                        $w->where('lab1_assigned', $lab)->orWhere('lab2_assigned', $lab);
                    });
                }

                // exacto por horas
                $ids = (clone $base)
                    ->where('start_time', $s->start_time)
                    ->where('end_time',   $s->end_time)
                    ->pluck('assignment_id');

                // fallback: solape con misma duración
                if ($ids->isEmpty()) {
                    $dur = strtotime($s->end_time) - strtotime($s->start_time);
                    $ids = (clone $base)
                        ->whereRaw('? < end_time AND ? > start_time', [$s->start_time, $s->end_time])
                        ->whereRaw('TIME_TO_SEC(TIMEDIFF(end_time,start_time)) = ?', [$dur])
                        ->pluck('assignment_id');
                }

                if ($ids->isNotEmpty()) {
                    DB::table('manual_schedule_assignments')
                        ->whereIn('assignment_id', $ids->all())
                        ->delete();
                }

                DB::commit();
                return response()->json(['status'=>'success','message'=>'Eliminado de schedule y espejo manual.']);
            }

            // 2) ¿O está en manual?
            $m = DB::table('manual_schedule_assignments')->where('assignment_id', $aid)->first();
            if ($m) {
                DB::table('manual_schedule_assignments')->where('assignment_id', $aid)->delete();

                $baseS = DB::table('schedule_assignments')
                    ->where('group_id',   $m->group_id)
                    ->where('subject_id', $m->subject_id)
                    ->whereRaw('LOWER(schedule_day) = LOWER(?)', [$m->schedule_day]);

                if (!is_null($m->classroom_id)) {
                    $baseS->where('classroom_id', (int)$m->classroom_id);
                } else {
                    $lab = (int)($m->lab1_assigned ?: $m->lab2_assigned);
                    $baseS->where('lab_id', $lab);
                }

                $idsS = (clone $baseS)
                    ->where('start_time', $m->start_time)
                    ->where('end_time',   $m->end_time)
                    ->pluck('assignment_id');

                if ($idsS->isEmpty()) {
                    $dur = strtotime($m->end_time) - strtotime($m->start_time);
                    $idsS = (clone $baseS)
                        ->whereRaw('? < end_time AND ? > start_time', [$m->start_time, $m->end_time])
                        ->whereRaw('TIME_TO_SEC(TIMEDIFF(end_time,start_time)) = ?', [$dur])
                        ->pluck('assignment_id');
                }

                if ($idsS->isNotEmpty()) {
                    DB::table('schedule_assignments')
                        ->whereIn('assignment_id', $idsS->all())
                        ->delete();
                }

                DB::commit();
                return response()->json(['status'=>'success','message'=>'Eliminado de manual y espejo schedule.']);
            }

            DB::rollBack();
            return response()->json(['status'=>'error','message'=>'No se encontró la asignación ni en schedule ni en manual.'], 404);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Borrar intercambio (hard): '.$e->getMessage(), ['aid'=>$aid]);
            return response()->json(['status'=>'error','message'=>'Error al eliminar: '.$e->getMessage()], 500);
        }
    }


    // ============================
    // 3) Eventos por GRUPO (para el calendario)
    // ============================
    public function eventosPorGrupo($grupo_id)
    {
        $group_id = (int)$grupo_id;

        $rows = DB::table('schedule_assignments as s')
            ->join('subjects as m', 's.subject_id','=','m.subject_id')
            ->join('groups as g',   's.group_id','=','g.group_id')
            ->select(
                's.assignment_id','s.subject_id','m.subject_name',
                's.start_time','s.end_time','s.schedule_day',
                's.group_id','g.group_name',
                's.tipo_espacio','s.classroom_id','s.lab_id'
            )
            ->where('s.group_id',$group_id)
            ->where('s.estado','activo')
            ->orderBy('s.start_time')
            ->get();

        return response()->json($rows);
    }


    // ============================
    // Helpers
    // ============================
    private function canonicalDay(string $d): string
    {
        $k = mb_strtolower(trim($d),'UTF-8');
        $map = [
            'lunes'=>'Lunes','martes'=>'Martes',
            'miercoles'=>'Miércoles','miércoles'=>'Miércoles',
            'jueves'=>'Jueves','viernes'=>'Viernes',
            'sabado'=>'Sábado','sábado'=>'Sábado','domingo'=>'Domingo'
        ];
        return $map[$k] ?? 'Lunes';
    }

    private function labForSchedule(array $slot)
    {
        if (!empty($slot['lab1_assigned'])) return (int)$slot['lab1_assigned'];
        if (!empty($slot['lab2_assigned'])) return (int)$slot['lab2_assigned'];
        return null;
    }

    private function minify($row)
    {
        return [
            'assignment_id'=>$row->assignment_id,
            'start_time'=>$row->start_time, 'end_time'=>$row->end_time,
            'schedule_day'=>$row->schedule_day,
            'classroom_id'=>$row->classroom_id,
            'lab1_assigned'=>$row->lab1_assigned,
            'lab2_assigned'=>$row->lab2_assigned,
            'tipo_espacio'=>$row->tipo_espacio,
            'group_id'=>$row->group_id, 'teacher_id'=>$row->teacher_id, 'subject_id'=>$row->subject_id
        ];
    }

    private function validaConflictosMovimiento($row, array $slot, array $exclude = []): ?string
    {
        $start = $slot['start_time'];
        $end   = $slot['end_time'];
        $day   = $slot['schedule_day'];

        $ex = function($q) use ($exclude){
            if (!empty($exclude)) $q->whereNotIn('assignment_id',$exclude);
        };

        // Espacio (manual)
        if ($slot['tipo_espacio'] === 'Laboratorio') {
            $exists = DB::table('manual_schedule_assignments')
                ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
                ->whereRaw('? < end_time AND ? > start_time',[$start,$end])
                ->where(function($w) use ($slot){
                    $lab = (int)($slot['lab1_assigned'] ?: $slot['lab2_assigned']);
                    $w->where('lab1_assigned',$lab)->orWhere('lab2_assigned',$lab);
                })
                ->where(function($q) use ($ex){ $ex($q); })
                ->where('estado','activo')
                ->exists();
            if ($exists) return 'El laboratorio ya está ocupado en ese horario.';
        } else {
            $exists = DB::table('manual_schedule_assignments')
                ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
                ->whereRaw('? < end_time AND ? > start_time',[$start,$end])
                ->where('classroom_id',(int)$slot['classroom_id'])
                ->where(function($q) use ($ex){ $ex($q); })
                ->where('estado','activo')
                ->exists();
            if ($exists) return 'El aula ya está ocupada en ese horario.';
        }

        // Espacio (schedule)
        if ($slot['tipo_espacio'] === 'Laboratorio') {
            $lab = (int)($slot['lab1_assigned'] ?: $slot['lab2_assigned']);
            $exists = DB::table('schedule_assignments')
                ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
                ->whereRaw('? < end_time AND ? > start_time',[$start,$end])
                ->where('lab_id',$lab)
                ->where(function($q) use ($ex){ $ex($q); })
                ->where('estado','activo')
                ->exists();
            if ($exists) return 'El laboratorio ya está ocupado en ese horario (oficial).';
        } else {
            $exists = DB::table('schedule_assignments')
                ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
                ->whereRaw('? < end_time AND ? > start_time',[$start,$end])
                ->where('classroom_id',(int)$slot['classroom_id'])
                ->where(function($q) use ($ex){ $ex($q); })
                ->where('estado','activo')
                ->exists();
            if ($exists) return 'El aula ya está ocupada en ese horario (oficial).';
        }

        // Profesor
        if (!is_null($row->teacher_id)) {
            $t = (int)$row->teacher_id;
            $exists = DB::table('manual_schedule_assignments')
                ->where('teacher_id',$t)
                ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
                ->whereRaw('? < end_time AND ? > start_time',[$start,$end])
                ->where(function($q) use ($ex){ $ex($q); })
                ->where('estado','activo')
                ->exists();
            if ($exists) return 'El profesor ya tiene una asignación en ese horario.';

            $exists = DB::table('schedule_assignments')
                ->where('teacher_id',$t)
                ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
                ->whereRaw('? < end_time AND ? > start_time',[$start,$end])
                ->where(function($q) use ($ex){ $ex($q); })
                ->where('estado','activo')
                ->exists();
            if ($exists) return 'El profesor ya tiene una asignación en ese horario (oficial).';
        }

        // Grupo
        $g = (int)$row->group_id;
        $exists = DB::table('manual_schedule_assignments')
            ->where('group_id',$g)
            ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
            ->whereRaw('? < end_time AND ? > start_time',[$start,$end])
            ->where(function($q) use ($ex){ $ex($q); })
            ->where('estado','activo')
            ->exists();
        if ($exists) return 'El grupo ya tiene una asignación en ese horario.';

        $exists = DB::table('schedule_assignments')
            ->where('group_id',$g)
            ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
            ->whereRaw('? < end_time AND ? > start_time',[$start,$end])
            ->where(function($q) use ($ex){ $ex($q); })
            ->where('estado','activo')
            ->exists();
        if ($exists) return 'El grupo ya tiene una asignación en ese horario (oficial).';

        return null;
    }

     public function mover(Request $r) {
        return app(\App\Http\Controllers\Horarios\HorarioManualController::class)->mover($r);
    }

}
