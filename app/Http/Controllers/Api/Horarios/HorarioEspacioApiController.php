<?php

namespace App\Http\Controllers\Api\Horarios;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class HorarioEspacioApiController extends Controller
{
    public function eventosSalon($classroom_id)
    {
        $ev = DB::table('schedule_assignments as sa')
            ->leftJoin('subjects as s','s.subject_id','=','sa.subject_id')
            ->leftJoin('groups as g','g.group_id','=','sa.group_id')
            ->where('sa.classroom_id', $classroom_id)
            ->select(
                'sa.assignment_id as id',
                'sa.group_id',
                'g.group_name',
                'sa.subject_id',
                's.subject_name',
                'sa.start_time',
                'sa.end_time',
                'sa.schedule_day as day',
                'sa.tipo_espacio'
            )
            ->orderBy('sa.schedule_day')
            ->orderBy('sa.start_time')
            ->get();
        return response()->json($ev);
    }

    public function eventosLaboratorio($lab_id)
    {
        $ev = DB::table('schedule_assignments as sa')
            ->leftJoin('subjects as s','s.subject_id','=','sa.subject_id')
            ->leftJoin('groups as g','g.group_id','=','sa.group_id')
            ->where('sa.lab_id', $lab_id)
            ->select(
                'sa.assignment_id as id',
                'sa.group_id',
                'g.group_name',
                'sa.subject_id',
                's.subject_name',
                'sa.start_time',
                'sa.end_time',
                'sa.schedule_day as day',
                'sa.tipo_espacio'
            )
            ->orderBy('sa.schedule_day')
            ->orderBy('sa.start_time')
            ->get();
        return response()->json($ev);
    }
}
