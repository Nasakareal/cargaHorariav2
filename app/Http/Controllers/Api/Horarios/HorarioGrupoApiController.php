<?php

namespace App\Http\Controllers\Api\Horarios;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class HorarioGrupoApiController extends Controller
{
    // GET /api/horarios/grupos
    public function index(Request $request)
    {
        $rows = DB::table('groups as g')
            ->leftJoin('shifts as s', 's.shift_id', '=', 'g.turn_id') // <-- CLAVE
            ->select(
                'g.group_id as id',
                'g.group_name as nombre',
                DB::raw('COALESCE(s.shift_name, s.name) as turno'), // <-- nombre legible
                'g.program_id'
            )
            ->orderBy('g.group_name')
            ->paginate(50);

        return response()->json($rows);
    }

    // GET /api/horarios/grupos/{grupo_id}
    public function show($grupo_id)
    {
        $g = DB::table('groups as g')
            ->leftJoin('shifts as s', 's.shift_id', '=', 'g.turn_id')
            ->where('g.group_id', $grupo_id)
            ->select(
                'g.group_id as id',
                'g.group_name as nombre',
                DB::raw('COALESCE(s.shift_name, s.name) as turno'),
                'g.program_id'
            )
            ->first();

        if (!$g) return response()->json(['message' => 'Grupo no encontrado'], 404);
        return response()->json($g);
    }

    // GET /api/horarios/grupos/{grupo_id}/eventos
    public function eventos($grupo_id)
    {
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
            ->orderBy('sa.schedule_day')
            ->orderBy('sa.start_time')
            ->get();

        return response()->json($ev);
    }
}
