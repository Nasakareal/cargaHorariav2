<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\ActividadGeneral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class SubjectApiController extends Controller
{
    // ===================== INDEX (GET /api/v1/materias) =====================
    public function index(Request $request)
    {
        $q          = trim((string) $request->query('q', ''));
        $programId  = $request->query('programa_id');
        $termId     = $request->query('term_id');
        $estado     = $request->query('estado'); // ACTIVO/INACTIVO opcional
        $perPage    = (int) ($request->query('per_page', 15));

        // Subqueries para traer un programa/term "fallback" desde la tabla puente (si aplica)
        $progSub = DB::table('program_term_subjects')
            ->select('subject_id', DB::raw('MIN(program_id) AS program_id'))
            ->groupBy('subject_id');

        $termSub = DB::table('program_term_subjects')
            ->select('subject_id', DB::raw('MIN(term_id) AS term_id'))
            ->groupBy('subject_id');

        $rows = DB::table('subjects as s')
            ->leftJoinSub($progSub, 'pp', 'pp.subject_id', '=', 's.subject_id')
            ->leftJoin('programs as p_dir', 'p_dir.program_id', '=', 's.program_id')
            ->leftJoin('programs as p_puente', function ($j) {
                $j->on('p_puente.program_id', '=', 'pp.program_id');
            })
            ->leftJoinSub($termSub, 'tt', 'tt.subject_id', '=', 's.subject_id')
            ->leftJoin('terms as t_dir', 't_dir.term_id', '=', 's.term_id')
            ->leftJoin('terms as t_puente', function ($j) {
                $j->on('t_puente.term_id', '=', 'tt.term_id');
            })
            ->when($q !== '', function ($qry) use ($q) {
                $qry->where('s.subject_name', 'like', "%{$q}%");
            })
            ->when($programId, function ($qry) use ($programId) {
                $qry->where(function ($w) use ($programId) {
                    $w->where('s.program_id', $programId)
                      ->orWhere('pp.program_id', $programId);
                });
            })
            ->when($termId, function ($qry) use ($termId) {
                $qry->where(function ($w) use ($termId) {
                    $w->where('s.term_id', $termId)
                      ->orWhere('tt.term_id', $termId);
                });
            })
            ->when($estado, fn($qry) => $qry->where('s.estado', $estado))
            ->select([
                's.subject_id',
                's.subject_name',
                's.weekly_hours',
                's.max_consecutive_class_hours',
                's.unidades',
                's.program_id',
                's.term_id',
                's.estado',
                's.fyh_creacion',
                DB::raw('COALESCE(p_dir.program_name, p_puente.program_name) AS programa_nombre'),
                DB::raw('COALESCE(t_dir.term_name, t_puente.term_name) AS term_nombre'),
            ])
            ->orderBy('s.subject_name')
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
        ]);
    }

    // ===================== SHOW (GET /api/v1/materias/{id}) =====================
    public function show($id)
    {
        $materia = DB::table('subjects as s')
            ->leftJoin('programs as p', 'p.program_id', '=', 's.program_id')
            ->leftJoin('terms as t',    't.term_id',    '=', 's.term_id')
            ->where('s.subject_id', (int)$id)
            ->select('s.*', 'p.program_name', 't.term_name')
            ->first();

        if (!$materia) {
            return response()->json(['message' => 'Materia no encontrada'], 404);
        }

        // Relaciones N–N desde program_term_subjects (solo a modo informativo)
        $rel = DB::table('program_term_subjects as pts')
            ->join('programs as p', 'p.program_id', '=', 'pts.program_id')
            ->join('terms as t',    't.term_id',    '=', 'pts.term_id')
            ->where('pts.subject_id', (int)$id)
            ->orderBy('p.program_name')
            ->orderBy('t.term_id')
            ->get(['p.program_name','t.term_name']);

        return response()->json([
            'data' => $materia,
            'relaciones' => $rel,
        ]);
    }

    // ===================== STORE (POST /api/v1/materias) =====================
    public function store(Request $request)
    {
        $data = $request->validate([
            'subject_name' => [
                'required', 'string', 'max:255',
                Rule::unique('subjects', 'subject_name')
                    ->where(fn($q) => $q->where('program_id', $request->program_id)
                                        ->where('term_id',    $request->term_id)),
            ],
            'weekly_hours'                => 'required|integer|min:1',
            'max_consecutive_class_hours' => 'required|integer|min:1',
            'program_id'                  => 'required|integer|exists:programs,program_id',
            'term_id'                     => 'required|integer|exists:terms,term_id',
            'unidades'                    => 'required|integer|min:1',
            'estado'                      => 'nullable|in:ACTIVO,INACTIVO',
        ]);

        $data['subject_name'] = preg_replace('/\s+/', ' ', trim($data['subject_name']));
        $data['estado']       = $data['estado'] ?? 'ACTIVO';

        try {
            DB::beginTransaction();

            $materia = Subject::create([
                'subject_name'                => $data['subject_name'],
                'weekly_hours'                => (int) $data['weekly_hours'],
                'max_consecutive_class_hours' => (int) $data['max_consecutive_class_hours'],
                'program_id'                  => (int) $data['program_id'],
                'term_id'                     => (int) $data['term_id'],
                'unidades'                    => (int) $data['unidades'],
                'estado'                      => $data['estado'],
            ]);

            // Bitácora (si existe el modelo)
            try {
                ActividadGeneral::registrar('CREAR', 'subjects', $materia->subject_id, "Creó materia {$materia->subject_name}");
            } catch (\Throwable $e) {
                Log::warning('ActividadGeneral no disponible (CREAR subjects): '.$e->getMessage());
            }

            DB::commit();
            return response()->json(['data' => $materia], 201);
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Error BD al crear materia', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);
            return response()->json(['message' => 'Error de base de datos al crear la materia'], 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error inesperado al crear materia', ['msg' => $e->getMessage()]);
            return response()->json(['message' => 'Error inesperado al crear la materia'], 500);
        }
    }

    // ===================== UPDATE (PUT/PATCH /api/v1/materias/{id}) =====================
    public function update(Request $request, $id)
    {
        $materia = Subject::where('subject_id', (int)$id)->first();
        if (!$materia) {
            return response()->json(['message' => 'La materia no existe'], 404);
        }

        $data = $request->validate([
            'subject_name' => [
                'required', 'string', 'max:255',
                Rule::unique('subjects', 'subject_name')
                    ->where(fn($q) => $q->where('program_id', $request->program_id)
                                        ->where('term_id',    $request->term_id))
                    ->ignore($materia->subject_id, 'subject_id'),
            ],
            'weekly_hours'                => 'required|integer|min:1',
            'max_consecutive_class_hours' => 'required|integer|min:1',
            'program_id'                  => 'required|integer|exists:programs,program_id',
            'term_id'                     => 'required|integer|exists:terms,term_id',
            'unidades'                    => 'required|integer|min:1',
            'estado'                      => 'required|in:ACTIVO,INACTIVO',
        ]);

        $data['subject_name'] = preg_replace('/\s+/', ' ', trim($data['subject_name']));

        try {
            DB::beginTransaction();

            $materia->update([
                'subject_name'                => $data['subject_name'],
                'weekly_hours'                => (int) $data['weekly_hours'],
                'max_consecutive_class_hours' => (int) $data['max_consecutive_class_hours'],
                'program_id'                  => (int) $data['program_id'],
                'term_id'                     => (int) $data['term_id'],
                'unidades'                    => (int) $data['unidades'],
                'estado'                      => $data['estado'],
            ]);

            try {
                ActividadGeneral::registrar('ACTUALIZAR', 'subjects', $materia->subject_id, "Actualizó materia {$materia->subject_name}");
            } catch (\Throwable $e) {
                Log::warning('ActividadGeneral no disponible (ACTUALIZAR subjects): '.$e->getMessage());
            }

            DB::commit();
            return response()->json(['data' => $materia]);
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Error BD al actualizar materia', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return response()->json(['message' => 'Error de base de datos al actualizar la materia'], 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error inesperado al actualizar materia', ['msg'=>$e->getMessage()]);
            return response()->json(['message' => 'Error inesperado al actualizar la materia'], 500);
        }
    }

    // ===================== DESTROY (DELETE /api/v1/materias/{id}) =====================
    public function destroy($id)
    {
        $materia = Subject::where('subject_id', (int)$id)->first();
        if (!$materia) {
            return response()->json(['message' => 'La materia no existe'], 404);
        }

        // No permitir borrar si está en uso
        $enUso = DB::table('teacher_subjects')->where('subject_id', $materia->subject_id)->exists()
              || DB::table('schedule_assignments')->where('subject_id', $materia->subject_id)->exists()
              || DB::table('manual_schedule_assignments')->where('subject_id', $materia->subject_id)->exists();

        if ($enUso) {
            return response()->json(['message' => 'No se puede eliminar una materia que ya está asignada'], 409);
        }

        try {
            DB::beginTransaction();

            DB::table('program_term_subjects')->where('subject_id', $materia->subject_id)->delete();
            $materia->delete();

            try {
                ActividadGeneral::registrar('ELIMINAR', 'subjects', (int)$id, "Eliminó materia {$materia->subject_name}");
            } catch (\Throwable $e) {
                Log::warning('ActividadGeneral no disponible (ELIMINAR subjects): '.$e->getMessage());
            }

            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Materia eliminada']);
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Error BD al eliminar materia', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return response()->json(['message' => 'Error de base de datos al eliminar la materia'], 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error inesperado al eliminar materia', ['msg'=>$e->getMessage()]);
            return response()->json(['message' => 'Error inesperado al eliminar la materia'], 500);
        }
    }
}
