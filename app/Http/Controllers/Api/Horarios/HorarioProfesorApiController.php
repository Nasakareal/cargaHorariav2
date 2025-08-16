<?php

namespace App\Http\Controllers\Api\Horarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;

class HorarioProfesorApiController extends Controller
{
    // GET /api/v1/horarios/profesores
    public function index(Request $request)
    {
        try {
            $q = DB::table('teachers as t');

            // si existe columna estado, filtra activos
            if (Schema::hasColumn('teachers', 'estado')) {
                $q->whereIn('t.estado', ['1','ACTIVO','activo']);
            }

            // filtro textual opcional ?q=...
            if ($request->filled('q')) {
                $term = '%'.trim($request->get('q')).'%';
                $q->where('t.teacher_name', 'like', $term);
            }

            $rows = $q->select('t.teacher_id as id', 't.teacher_name as nombre')
                      ->orderBy('t.teacher_name')
                      ->paginate(50)
                      ->appends($request->query());

            return response()->json($rows);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Error al consultar profesores',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // GET /api/v1/horarios/profesores/{profesor_id}
    public function show($profesor_id)
    {
        try {
            $p = DB::table('teachers as t')
                ->when(Schema::hasColumn('teachers','estado'), function($q){
                    $q->whereIn('t.estado', ['1','ACTIVO','activo']);
                })
                ->where('t.teacher_id', $profesor_id)
                ->select('t.teacher_id as id', 't.teacher_name as nombre')
                ->first();

            if (!$p) {
                return response()->json(['message' => 'Profesor no encontrado'], 404);
            }
            return response()->json($p);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Error al consultar profesor',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // GET /api/v1/horarios/profesores/{profesor_id}/eventos
    public function eventos($profesor_id)
    {
        try {
            $ev = DB::table('schedule_assignments as sa')
                ->leftJoin('subjects as s',   's.subject_id',    '=', 'sa.subject_id')
                ->leftJoin('groups as g',     'g.group_id',      '=', 'sa.group_id')
                ->leftJoin('classrooms as c', 'c.classroom_id',  '=', 'sa.classroom_id')
                ->leftJoin('labs as l',       'l.lab_id',        '=', 'sa.lab_id')
                ->leftJoin('teachers as t',   't.teacher_id',    '=', 'sa.teacher_id')
                ->where('sa.teacher_id', $profesor_id)
                ->when(Schema::hasColumn('schedule_assignments','estado'), function($q){
                    $q->whereIn('sa.estado', ['1','ACTIVO','activo']);
                })
                ->selectRaw("
                    sa.assignment_id as id,
                    sa.group_id,
                    g.group_name,
                    sa.subject_id,
                    s.subject_name,
                    sa.teacher_id,
                    t.teacher_name,
                    sa.classroom_id,
                    c.classroom_name,
                    sa.lab_id,
                    l.lab_name,
                    sa.start_time,
                    sa.end_time,
                    sa.schedule_day as day,
                    sa.tipo_espacio
                ")
                // ordena días en español + hora
                ->orderByRaw("
                    FIELD(sa.schedule_day,
                        'Lunes','Martes','Miércoles','Miercoles',
                        'Jueves','Viernes','Sábado','Sabado','Domingo'
                    )
                ")
                ->orderBy('sa.start_time')
                ->get();

            return response()->json($ev);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Error al consultar eventos del profesor',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
