<?php

namespace App\Http\Controllers\Api\Horarios;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class HorarioGrupoApiController extends Controller
{
    // GET /api/v1/horarios/grupos
    public function index(Request $request)
    {
        try {
            $rows = DB::table('groups as g')
                ->leftJoin('shifts as s', 's.shift_id', '=', 'g.turn_id')
                ->when(DB::getSchemaBuilder()->hasColumn('groups','estado'), function ($q) {
                    $q->whereIn('g.estado', ['1','ACTIVO','activo']);
                })
                ->select([
                    'g.group_id   as id',
                    'g.group_name as nombre',
                    's.shift_name as turno',
                    'g.program_id',
                ])
                ->orderBy('g.group_name')
                ->paginate(50);

            return response()->json($rows);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Error al consultar grupos',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // GET /api/v1/horarios/grupos/{grupo_id}
    public function show(int $grupo_id)
    {
        try {
            $g = DB::table('groups as g')
                ->leftJoin('shifts as s', 's.shift_id', '=', 'g.turn_id')
                ->where('g.group_id', $grupo_id)
                ->select([
                    'g.group_id   as id',
                    'g.group_name as nombre',
                    's.shift_name as turno',
                    'g.program_id',
                ])
                ->first();

            if (!$g) return response()->json(['message' => 'Grupo no encontrado'], 404);
            return response()->json($g);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Error al consultar grupo',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // GET /api/v1/horarios/grupos/{grupo_id}/eventos
    public function eventos(int $grupo_id)
    {
        try {
            $ev = DB::table('schedule_assignments as sa')
                ->leftJoin('subjects as s','s.subject_id','=','sa.subject_id')
                ->leftJoin('teachers as t','t.teacher_id','=','sa.teacher_id')
                ->leftJoin('classrooms as c','c.classroom_id','=','sa.classroom_id')
                ->leftJoin('labs as l','l.lab_id','=','sa.lab_id')
                ->where('sa.group_id', $grupo_id)
                ->when(DB::getSchemaBuilder()->hasColumn('schedule_assignments','estado'), function($q){
                    $q->whereIn('sa.estado', ['1','ACTIVO','activo']);
                })
                ->selectRaw('
                    sa.assignment_id as id,
                    sa.group_id,
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
                ')
                /* ordena días en español y luego por hora */
                ->orderByRaw("FIELD(sa.schedule_day,'Lunes','Martes','Miércoles','Miercoles','Jueves','Viernes','Sábado','Sabado','Domingo')")
                ->orderBy('sa.start_time')
                ->get();

            return response()->json($ev);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Error al consultar eventos del grupo',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
