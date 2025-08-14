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

    protected function cleanupScheduleForTeacherSubjects($tsIds, int $teacherId): void
    {
        $tsIdsArr = collect($tsIds)->values();

        if (Schema::hasTable('schedule_assignments')) {
            if (Schema::hasColumn('schedule_assignments', 'teacher_subject_id')) {
                DB::table('schedule_assignments')->whereIn('teacher_subject_id', $tsIdsArr)->delete();
            } else {
                $pairs = DB::table('teacher_subjects')
                    ->whereIn('teacher_subject_id', $tsIdsArr)
                    ->get(['subject_id', 'group_id']);

                foreach ($pairs as $p) {
                    DB::table('schedule_assignments')
                        ->where('teacher_id', $teacherId)
                        ->where('subject_id', $p->subject_id)
                        ->where('group_id', $p->group_id)
                        ->delete();
                }
            }
        }

        if (Schema::hasTable('manual_schedule_assignments')) {
            if (Schema::hasColumn('manual_schedule_assignments', 'teacher_subject_id')) {
                DB::table('manual_schedule_assignments')->whereIn('teacher_subject_id', $tsIdsArr)->delete();
            } else {
                $pairs = DB::table('teacher_subjects')
                    ->whereIn('teacher_subject_id', $tsIdsArr)
                    ->get(['subject_id', 'group_id']);

                foreach ($pairs as $p) {
                    DB::table('manual_schedule_assignments')
                        ->where('subject_id', $p->subject_id)
                        ->where('group_id', $p->group_id)
                        ->delete();
                }
            }
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
