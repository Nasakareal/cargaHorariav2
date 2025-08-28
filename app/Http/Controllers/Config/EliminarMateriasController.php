<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use App\Models\ActividadGeneral;

class EliminarMateriasController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $profesores = DB::table('teachers')
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where('teacher_name', 'like', "%{$q}%");
                if (Schema::hasColumn('teachers', 'email')) {
                    $qq->orWhere('email', 'like', "%{$q}%");
                }
            })
            ->orderBy('teacher_name')
            ->select('teacher_id', 'teacher_name', DB::raw(Schema::hasColumn('teachers','email') ? 'email' : "'' as email"))
            ->paginate(15)
            ->appends(['q' => $q]);

        return view('configuracion.eliminar_materias.index', compact('profesores', 'q'));
    }

    public function edit($id)
    {
        $profesor = DB::table('teachers')->where('teacher_id', $id)->first();
        if (!$profesor) {
            return redirect()->route('configuracion.eliminar-materias.index')
                ->with('error', 'El profesor no existe o ya fue eliminado.');
        }

        $grupos = $this->getTeacherGroups((int)$id);
        $asignadas = $this->getMateriasAsignadasQuery((int)$id)->get();

        return view('configuracion.eliminar_materias.edit', compact('profesor','grupos','asignadas'));
    }

    public function destroySelected(Request $request, $id)
    {
        $this->mustCanEliminar();

        $data = $request->validate([
            'teacher_subject_ids'   => 'array',
            'teacher_subject_ids.*' => 'integer',
            'subject_ids'           => 'array',
            'subject_ids.*'         => 'integer',
            'group_id'              => 'nullable|integer',
        ]);

        $tsIds = collect($data['teacher_subject_ids'] ?? []);

        if ($tsIds->isEmpty() && !empty($data['subject_ids'])) {
            $q = DB::table('teacher_subjects')
                ->where('teacher_id', (int)$id)
                ->whereIn('subject_id', $data['subject_ids']);
            if (!empty($data['group_id'])) {
                $q->where('group_id', (int)$data['group_id']);
            }
            $tsIds = $q->pluck('teacher_subject_id');
        }

        $tsIds = $tsIds->unique()->values();
        if ($tsIds->isEmpty()) {
            return back()->with('error', 'No se seleccionaron asignaciones para eliminar.');
        }

        try {
            DB::beginTransaction();

            $this->cleanupScheduleForTeacherSubjects($tsIds, (int)$id);

            $borrados = DB::table('teacher_subjects')->whereIn('teacher_subject_id', $tsIds)->delete();

            $total = DB::table('teacher_subjects AS ts')
                ->join('subjects AS s', 's.subject_id', '=', 'ts.subject_id')
                ->where('ts.teacher_id', (int)$id)
                ->sum('s.weekly_hours');

            DB::table('teachers')
                ->where('teacher_id', (int)$id)
                ->update([
                    'hours'             => (int)($total ?? 0),
                    'fyh_actualizacion' => now(),
                ]);

            DB::commit();

            ActividadGeneral::registrar(
                'DELETE_SELECTED',
                'teacher_subjects',
                null,
                "Eliminó {$borrados} asignación(es) del profesor {$id}"
            );

            return back()->with('success', "Se eliminaron {$borrados} asignación(es) del profesor.");
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Error BD al eliminar asignaciones del profesor', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);
            return back()->with('error', 'Error de base de datos al eliminar asignaciones.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error inesperado al eliminar asignaciones del profesor', ['msg' => $e->getMessage()]);
            return back()->with('error', 'Ocurrió un error inesperado al eliminar asignaciones.');
        }
    }

    public function materiasAsignadas(Request $request, $id)
    {
        $groupId = $request->input('group_id');
        $q = $this->getMateriasAsignadasQuery((int)$id);

        if ($groupId) {
            $gid = (int) $groupId;

            if (!$this->teacherHasGroup((int)$id, $gid)) {
                return response()->json(['ok'=>true, 'data'=>[], 'count'=>0]);
            }
            $q->where('ts.group_id', $gid);
        }

        $rows = $q->get();
        return response()->json(['ok'=>true, 'data'=>$rows, 'count'=>$rows->count()]);
    }

    public function horasProfesor(Request $request, $id)
    {
        $groupId = $request->input('group_id');

        $q = DB::table('teacher_subjects AS ts')
            ->join('subjects AS s', 's.subject_id', '=', 'ts.subject_id')
            ->where('ts.teacher_id', (int)$id);

        if ($groupId) {
            $q->where('ts.group_id', (int)$groupId);
        }

        $total = (int) $q->sum('s.weekly_hours');

        return response()->json(['ok' => true, 'horas' => $total]);
    }

    /* ===================== HELPERS ===================== */

    protected function getMateriasAsignadasQuery(int $teacherId)
    {
        return DB::table('teacher_subjects as ts')
            ->join('subjects as s', 's.subject_id', '=', 'ts.subject_id')
            ->leftJoin('groups as g', 'g.group_id', '=', 'ts.group_id')
            ->where('ts.teacher_id', $teacherId)
            ->orderBy('g.group_name')
            ->orderBy('s.subject_name')
            ->select([
                'ts.teacher_subject_id',
                'ts.subject_id',
                's.subject_name',
                'ts.group_id',
                'g.group_name',
            ]);
    }

    /**
     * Regla:
     * - Si EXISTE espejo en manual para el mismo slot+recurso => NO borrar; poner teacher_id=NULL en ambas tablas.
     * - Si SOLO existe en schedule (sin espejo manual) => borrar el bloque en schedule.
     * - Nunca empatar por assignment_id entre tablas: se empata por subject_id, group_id, day, slot y recurso.
     */
    protected function cleanupScheduleForTeacherSubjects($tsIds, int $teacherId): void
    {
        $tsIdsArr = collect($tsIds)->values();
        if ($tsIdsArr->isEmpty()) return;

        // 1) Pairs subject_id/group_id de los TS seleccionados
        $pairs = DB::table('teacher_subjects')
            ->whereIn('teacher_subject_id', $tsIdsArr)
            ->get(['subject_id', 'group_id']);

        if ($pairs->isEmpty()) return;

        $subjects = $pairs->pluck('subject_id')->unique()->values();
        $groups   = $pairs->pluck('group_id')->unique()->values();

        // Normalizador de día (quita acentos y pone minúsculas)
        $normDay = function (?string $s) {
            $s = (string)($s ?? '');
            $s = mb_strtolower($s, 'UTF-8');
            $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
            $s = preg_replace('/[\x{0300}-\x{036f}]/u', '', $s);
            return $s;
        };

        // Función overlap con misma duración
        $sameDurationOverlap = function ($sStart, $sEnd, $mStart, $mEnd) {
            if (!$sStart || !$sEnd || !$mStart || !$mEnd) return false;
            $ss = strtotime($sStart);
            $se = strtotime($sEnd);
            $ms = strtotime($mStart);
            $me = strtotime($mEnd);
            if (!$ss || !$se || !$ms || !$me) return false;
            $durS = $se - $ss;
            $durM = $me - $ms;
            if ($durS !== $durM) return false;
            return ($sStart < $mEnd) && ($sEnd > $mStart);
        };

        // 2) Traer filas relevantes de ambas tablas (filtradas por subject/group)
        $schedRows = collect();
        $manualRows = collect();

        if (Schema::hasTable('schedule_assignments')) {
            $schedRows = DB::table('schedule_assignments')
                ->whereIn('subject_id', $subjects)
                ->whereIn('group_id', $groups)
                ->get([
                    'assignment_id','subject_id','group_id','teacher_id',
                    'classroom_id','lab_id',
                    'start_time','end_time','schedule_day','estado','tipo_espacio'
                ]);
        }

        if (Schema::hasTable('manual_schedule_assignments')) {
            $manualRows = DB::table('manual_schedule_assignments')
                ->whereIn('subject_id', $subjects)
                ->whereIn('group_id', $groups)
                ->get([
                    'assignment_id','subject_id','group_id','teacher_id',
                    'classroom_id','lab1_assigned','lab2_assigned',
                    'start_time','end_time','schedule_day','estado','tipo_espacio'
                ]);
        }

        // 3) Índice manual por (subject,group,day_norm,recurso,times)
        $manualByKey = [];
        foreach ($manualRows as $m) {
            $dayKey = $normDay($m->schedule_day);
            $lab = (int)($m->lab1_assigned ?: $m->lab2_assigned ?: 0);
            $resType = $lab > 0 ? 'lab' : 'aula';
            $resId   = $lab > 0 ? $lab : (int)($m->classroom_id ?: 0);

            $key = implode('|', [
                (int)$m->subject_id, (int)$m->group_id,
                $dayKey, $resType, $resId,
                (string)$m->start_time, (string)$m->end_time
            ]);

            $manualByKey[$key] = ($manualByKey[$key] ?? []);
            $manualByKey[$key][] = $m;
        }

        // 4) Procesar schedule uno por uno según REGLA
        $toNullSched = [];
        $toDeleteSched = [];
        $toNullManual = [];

        foreach ($schedRows as $s) {
            if ((int)$s->teacher_id !== $teacherId) {
                // No se está eliminando esta relación del profe; saltar.
                continue;
            }

            $dayKey = $normDay($s->schedule_day);
            $resType = ((int)$s->lab_id > 0) ? 'lab' : 'aula';
            $resId   = ((int)$s->lab_id > 0) ? (int)$s->lab_id : (int)($s->classroom_id ?: 0);

            // a) Buscar espejo EXACTO (start/end iguales)
            $exactKey = implode('|', [
                (int)$s->subject_id, (int)$s->group_id, $dayKey, $resType, $resId,
                (string)$s->start_time, (string)$s->end_time
            ]);

            $matches = collect($manualByKey[$exactKey] ?? []);

            // b) Si no hay exacto, permitir overlap con misma duración + mismo recurso
            if ($matches->isEmpty()) {
                $candidates = $manualRows->filter(function($m) use ($s, $dayKey, $resType, $resId, $sameDurationOverlap, $normDay) {
                    $lab = (int)($m->lab1_assigned ?: $m->lab2_assigned ?: 0);
                    $mResType = $lab > 0 ? 'lab' : 'aula';
                    $mResId   = $lab > 0 ? $lab : (int)($m->classroom_id ?: 0);

                    return
                        ((int)$m->subject_id === (int)$s->subject_id) &&
                        ((int)$m->group_id   === (int)$s->group_id) &&
                        ($normDay($m->schedule_day) === $dayKey) &&
                        ($mResType === $resType) &&
                        ($mResId   === $resId) &&
                        $sameDurationOverlap($s->start_time, $s->end_time, $m->start_time, $m->end_time);
                });
                $matches = $candidates->values();
            }

            if ($matches->isNotEmpty()) {
                // REGLA #1: hay espejo manual -> NO borrar, solo poner teacher_id=NULL en ambas
                $toNullSched[] = (int)$s->assignment_id;
                foreach ($matches as $m) {
                    $toNullManual[] = (int)$m->assignment_id;
                }
            } else {
                // REGLA #2: NO hay espejo manual -> borrar en schedule
                $toDeleteSched[] = (int)$s->assignment_id;
            }
        }

        // 5) Aplicar cambios en BD
        if (!empty($toNullSched) && Schema::hasTable('schedule_assignments')) {
            DB::table('schedule_assignments')
                ->whereIn('assignment_id', $toNullSched)
                ->update(['teacher_id' => null, 'fyh_actualizacion' => now()]);
        }

        if (!empty($toDeleteSched) && Schema::hasTable('schedule_assignments')) {
            DB::table('schedule_assignments')
                ->whereIn('assignment_id', $toDeleteSched)
                ->delete();
        }

        if (!empty($toNullManual) && Schema::hasTable('manual_schedule_assignments')) {
            DB::table('manual_schedule_assignments')
                ->whereIn('assignment_id', array_values(array_unique($toNullManual)))
                ->update(['teacher_id' => null, 'fyh_actualizacion' => now()]);
        }

        if (Schema::hasTable('manual_schedule_assignments')) {
            DB::table('manual_schedule_assignments')
                ->whereIn('subject_id', $subjects)
                ->whereIn('group_id', $groups)
                ->where('teacher_id', $teacherId)
                ->update(['teacher_id' => null, 'fyh_actualizacion' => now()]);
        }
    }

    protected function mustCanEliminar(): void
    {
        if (!Auth::user()?->can('eliminar materias')) {
            abort(403, 'No tienes permiso para eliminar materias.');
        }
    }

    protected function getTeacherGroups(int $teacherId)
    {
        return DB::table('teacher_subjects as ts')
            ->join('groups as g', 'g.group_id', '=', 'ts.group_id')
            ->where('ts.teacher_id', $teacherId)
            ->whereNotNull('ts.group_id')
            ->select('g.group_id', 'g.group_name')
            ->distinct()
            ->orderBy('g.group_name')
            ->get();
    }

    protected function teacherHasGroup(int $teacherId, int $groupId): bool
    {
        return DB::table('teacher_subjects')
            ->where('teacher_id', $teacherId)
            ->where('group_id',  $groupId)
            ->exists();
    }
}
