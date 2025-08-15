<?php

namespace App\Http\Controllers\Api\Config;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class HorarioPasadoApiController extends Controller
{
    public function index()
    {
        $fechas = DB::table('schedule_history')
            ->selectRaw('DISTINCT DATE(fecha_registro) as fecha')
            ->orderByDesc('fecha')
            ->get();

        return response()->json($fechas);
    }

    public function show($fecha)
    {
        $rows = DB::table('schedule_history')
            ->whereDate('fecha_registro', $fecha)
            ->get();

        return response()->json($rows);
    }
}
