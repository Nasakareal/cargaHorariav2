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
            $s = DB::table('schedule_assignments')->where('assignment_id', $aid)->first();
            if ($s) {
                DB::table('schedule_assignments')->where('assignment_id', $aid)->delete();

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

                $ids = (clone $base)
                    ->where('start_time', $s->start_time)
                    ->where('end_time',   $s->end_time)
                    ->pluck('assignment_id');

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
            if (!empty($exclude)) $q->whereNotIn('assignment_id', $exclude);
        };

        // Detectar si HAY espacio realmente
        $hasAula = !is_null($slot['classroom_id']) && $slot['classroom_id'] !== '' && (int)$slot['classroom_id'] > 0;
        $labFromSlot = (int)($slot['lab_id'] ?? 0);
        $lab1 = (int)($slot['lab1_assigned'] ?? 0);
        $lab2 = (int)($slot['lab2_assigned'] ?? 0);
        $hasLab = $labFromSlot > 0 || $lab1 > 0 || $lab2 > 0;
        $hasEspacio = $hasAula || $hasLab;

        // 1) Solo validar ESPACIO si existe alguno
        if ($hasEspacio) {
            // --- manual ---
            if ($hasLab) {
                $lab = $labFromSlot ?: ($lab1 ?: $lab2);
                $exists = DB::table('manual_schedule_assignments')
                    ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
                    ->whereRaw('? < end_time AND ? > start_time',[$start,$end])
                    ->where(function($w) use ($lab){
                        $w->where('lab1_assigned',$lab)->orWhere('lab2_assigned',$lab);
                    })
                    ->where(function($q) use ($ex){ $ex($q); })
                    ->where('estado','activo')
                    ->exists();
                if ($exists) return 'El laboratorio ya está ocupado en ese horario.';
            }
            if ($hasAula) {
                $exists = DB::table('manual_schedule_assignments')
                    ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
                    ->whereRaw('? < end_time AND ? > start_time',[$start,$end])
                    ->where('classroom_id',(int)$slot['classroom_id'])
                    ->where(function($q) use ($ex){ $ex($q); })
                    ->where('estado','activo')
                    ->exists();
                if ($exists) return 'El aula ya está ocupada en ese horario.';
            }

            // --- oficial (schedule) ---
            if ($hasLab) {
                $lab = $labFromSlot ?: ($lab1 ?: $lab2);
                $exists = DB::table('schedule_assignments')
                    ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
                    ->whereRaw('? < end_time AND ? > start_time',[$start,$end])
                    ->where('lab_id',$lab)
                    ->where(function($q) use ($ex){ $ex($q); })
                    ->where('estado','activo')
                    ->exists();
                if ($exists) return 'El laboratorio ya está ocupado en ese horario (oficial).';
            }
            if ($hasAula) {
                $exists = DB::table('schedule_assignments')
                    ->whereRaw('LOWER(schedule_day)=LOWER(?)',[$day])
                    ->whereRaw('? < end_time AND ? > start_time',[$start,$end])
                    ->where('classroom_id',(int)$slot['classroom_id'])
                    ->where(function($q) use ($ex){ $ex($q); })
                    ->where('estado','activo')
                    ->exists();
                if ($exists) return 'El aula ya está ocupada en ese horario (oficial).';
            }
        }

        // 2) Validación PROFESOR (si hay)
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

        // 3) Validación GRUPO (siempre)
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


    public function mover(Request $r)
    {
        $aid   = (int)$r->input('assignment_id');
        $day   = $this->canonicalDay((string)$r->input('schedule_day'));
        $start = (string)$r->input('start_time');
        $end   = (string)$r->input('end_time');

        if (!$aid || !$day || !$start || !$end) {
            return response()->json(['status'=>'error','message'=>'Parámetros incompletos'], 422);
        }

        try {
            DB::beginTransaction();

            // 1) Intentar mover en schedule_assignments
            $row = DB::table('schedule_assignments')->where('assignment_id',$aid)->first();
            if ($row) {
                $slot = [
                    'start_time'=>$start, 'end_time'=>$end, 'schedule_day'=>$day,
                    'classroom_id'=>$row->classroom_id,
                    'lab_id'=>$row->lab_id,
                    // Para compatibilidad con la validación (manual)
                    'lab1_assigned'=>null, 'lab2_assigned'=>null,
                    'group_id'=>$row->group_id, 'teacher_id'=>$row->teacher_id, 'subject_id'=>$row->subject_id,
                ];

                $msg = $this->validaConflictosMovimiento($row, $slot, [$aid]);
                if ($msg) { DB::rollBack(); return response()->json(['status'=>'error','message'=>$msg], 409); }

                DB::table('schedule_assignments')->where('assignment_id',$aid)->update([
                    'schedule_day'=>$day, 'start_time'=>$start, 'end_time'=>$end,
                    'fyh_actualizacion'=>Carbon::now(),
                ]);

                // Espejo en manual (si existía el mismo bloque)
                DB::table('manual_schedule_assignments')
                    ->where('group_id',$row->group_id)
                    ->where('subject_id',$row->subject_id)
                    ->whereRaw('LOWER(schedule_day)=LOWER(?)', [$row->schedule_day])
                    ->where('start_time',$row->start_time)
                    ->where('end_time',$row->end_time)
                    ->when(!is_null($row->classroom_id), fn($q)=>$q->where('classroom_id',$row->classroom_id))
                    ->when(!is_null($row->lab_id), fn($q)=>$q->where(function($w) use ($row){
                        $w->where('lab1_assigned',$row->lab_id)->orWhere('lab2_assigned',$row->lab_id);
                    }))
                    ->update(['schedule_day'=>$day,'start_time'=>$start,'end_time'=>$end,'fyh_actualizacion'=>Carbon::now()]);

                DB::commit();
                return response()->json(['status'=>'success','message'=>'Materia movida (oficial).']);
            }

            // 2) Si no está en schedule, mover en manual
            $row = DB::table('manual_schedule_assignments')->where('assignment_id',$aid)->first();
            if ($row) {
                $slot = [
                    'start_time'=>$start, 'end_time'=>$end, 'schedule_day'=>$day,
                    'classroom_id'=>$row->classroom_id,
                    'lab1_assigned'=>$row->lab1_assigned, 'lab2_assigned'=>$row->lab2_assigned,
                    // Para compatibilidad con schedule
                    'lab_id'=>null,
                    'group_id'=>$row->group_id, 'teacher_id'=>$row->teacher_id, 'subject_id'=>$row->subject_id,
                ];

                $msg = $this->validaConflictosMovimiento($row, $slot, [$aid]);
                if ($msg) { DB::rollBack(); return response()->json(['status'=>'error','message'=>$msg], 409); }

                DB::table('manual_schedule_assignments')->where('assignment_id',$aid)->update([
                    'schedule_day'=>$day, 'start_time'=>$start, 'end_time'=>$end,
                    'fyh_actualizacion'=>Carbon::now(),
                ]);

                // Espejo en schedule (si existía el mismo bloque)
                $lab = (int)($row->lab1_assigned ?: $row->lab2_assigned);
                DB::table('schedule_assignments')
                    ->where('group_id',$row->group_id)
                    ->where('subject_id',$row->subject_id)
                    ->whereRaw('LOWER(schedule_day)=LOWER(?)', [$row->schedule_day])
                    ->where('start_time',$row->start_time)
                    ->where('end_time',$row->end_time)
                    ->when(!is_null($row->classroom_id), fn($q)=>$q->where('classroom_id',$row->classroom_id))
                    ->when($lab>0, fn($q)=>$q->where('lab_id',$lab))
                    ->update(['schedule_day'=>$day,'start_time'=>$start,'end_time'=>$end,'fyh_actualizacion'=>Carbon::now()]);

                DB::commit();
                return response()->json(['status'=>'success','message'=>'Materia movida (manual).']);
            }

            DB::rollBack();
            return response()->json(['status'=>'error','message'=>'No se encontró la asignación.'], 404);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Mover intercambio: '.$e->getMessage(), ['aid'=>$aid]);
            return response()->json(['status'=>'error','message'=>'Error al mover: '.$e->getMessage()], 500);
        }
    }


}
