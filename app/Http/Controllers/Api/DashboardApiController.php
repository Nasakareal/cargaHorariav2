<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardApiController extends Controller
{
    // GET /api/v1/dashboard/resumen
    public function resumen()
    {
        $q = DB::table('schedule_assignments as sa');

        if (DB::getSchemaBuilder()->hasColumn('schedule_assignments','estado')) {
            $q->whereIn('sa.estado', ['1','ACTIVO','activo']);
        }

        $row = $q->selectRaw("
            COUNT(DISTINCT CONCAT(sa.group_id, ':', sa.subject_id)) AS total_materias,
            COUNT(DISTINCT CASE
                WHEN sa.teacher_id IS NOT NULL AND sa.teacher_id <> 0
                THEN CONCAT(sa.group_id, ':', sa.subject_id)
            END) AS cubiertas,
            COUNT(DISTINCT sa.group_id) AS total_grupos
        ")->first();

        $totalMaterias = (int)($row->total_materias ?? 0);
        $cubiertas     = (int)($row->cubiertas ?? 0);
        $faltantes     = max($totalMaterias - $cubiertas, 0);
        $cobertura     = $totalMaterias > 0 ? $cubiertas / $totalMaterias : 0.0;

        return response()->json([
            'total_materias' => $totalMaterias,
            'cubiertas'      => $cubiertas,
            'faltantes'      => $faltantes,
            'total_grupos'   => (int)($row->total_grupos ?? 0),
            'cobertura'      => round($cobertura, 4),
        ]);
    }
}
