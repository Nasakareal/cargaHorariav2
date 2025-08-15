<?php

namespace App\Http\Controllers\Api\Horarios;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class HorarioProfesorApiController extends Controller
{
    public function index()
    {
        $rows = DB::table('teachers as t')
            ->select('t.teacher_id as id','t.teacher_name as nombre')
            ->orderBy('t.teacher_name')
            ->paginate(50);
        return response()->json($rows);
    }

    public function show($profesor_id)
    {
        $p = DB::table('teachers')->where('teacher_id',$profesor_id)->first();
        if (!$p) return response()->json(['message'=>'Profesor no encontrado'],404);
        return response()->json($p);
    }

    public function eventos($profesor_id)
    {
        $ev = DB::table('schedule_assignments as sa')
            ->leftJoin('subjects as s','s.subject_id','=','sa.subject_id')
            ->leftJoin('groups as g','g.group_id','=','sa.group_id')
            ->leftJoin('classrooms as c','c.classroom_id','=','sa.classroom_id')
            ->leftJoin('labs as l','l.lab_id','=','sa.lab_id')
            ->where('sa.teacher_id', $profesor_id)
            ->selectRaw('
                sa.assignment_id as id,
                sa.group_id,
                g.group_name,
                sa.subject_id,
                s.subject_name,
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
