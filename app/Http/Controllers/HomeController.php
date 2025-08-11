<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        // ===== Resumen global (cubiertas / no cubiertas) =====
        // Basado en group_subjects (lo que debe cubrirse) y teacher_subjects (lo asignado)
        $resumen = DB::table('group_subjects as gs')
            ->join('subjects as s', 's.subject_id', '=', 'gs.subject_id')
            ->leftJoin('teacher_subjects as ts', function ($q) {
                $q->on('ts.group_id',  '=', 'gs.group_id')
                  ->on('ts.subject_id','=', 'gs.subject_id');
            })
            ->selectRaw('SUM(CASE WHEN ts.teacher_id IS NOT NULL THEN 1 ELSE 0 END) AS materias_cubiertas')
            ->selectRaw('SUM(CASE WHEN ts.teacher_id IS NULL     THEN 1 ELSE 0 END) AS materias_no_cubiertas')
            ->first();

        $cubiertas    = (int)($resumen->materias_cubiertas ?? 0);
        $noCubiertas  = (int)($resumen->materias_no_cubiertas ?? 0);
        $total        = $cubiertas + $noCubiertas;

        $porcCubiertas    = $total > 0 ? round(($cubiertas / $total) * 100, 2) : 0.0;
        $porcNoCubiertas  = $total > 0 ? round(($noCubiertas / $total) * 100, 2) : 0.0;

        // ===== Grupos con detalle de faltantes =====
        $grupos = DB::table('groups as g')
            ->join('group_subjects as gs', 'gs.group_id', '=', 'g.group_id')
            ->join('subjects as s', 's.subject_id', '=', 'gs.subject_id')
            ->leftJoin('teacher_subjects as ts', function ($q) {
                $q->on('ts.group_id',  '=', 'g.group_id')
                  ->on('ts.subject_id','=', 's.subject_id');
            })
            ->groupBy('g.group_id','g.group_name')
            ->orderBy('g.group_name')
            ->select([
                'g.group_id',
                'g.group_name',
                DB::raw('GROUP_CONCAT(CASE WHEN ts.teacher_id IS NULL THEN s.subject_name END ORDER BY s.subject_name SEPARATOR ", ") AS materias_faltantes'),
                DB::raw('SUM(CASE WHEN ts.teacher_id IS NOT NULL THEN 1 ELSE 0 END) AS materias_asignadas'),
                DB::raw('SUM(CASE WHEN ts.teacher_id IS NULL     THEN 1 ELSE 0 END) AS materias_no_cubiertas'),
                DB::raw('COUNT(s.subject_id) AS total_materias'),
            ])
            ->get();

        // Solo los que aún tienen faltantes
        $gruposConFaltantes = $grupos->filter(fn($g) => (int)$g->materias_no_cubiertas > 0)->values();

        return view('home', [
            'usuario'                   => auth()->user(),
            'materias_cubiertas'        => $cubiertas,
            'materias_no_cubiertas'     => $noCubiertas,
            'total_materias'            => $total,
            'porcentaje_cubiertas'      => $porcCubiertas,
            'porcentaje_no_cubiertas'   => $porcNoCubiertas,
            'grupos_con_faltantes'      => $gruposConFaltantes,
        ]);
    }
}
