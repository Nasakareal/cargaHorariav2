<?php

namespace App\Http\Controllers\Api\Config;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class CalendarioEscolarApiController extends Controller
{
    public function index()
    {
        $rows = DB::table('calendario_escolar')
            ->orderBy('fecha_inicio')
            ->get();
        return response()->json($rows);
    }

    public function show($id)
    {
        $row = DB::table('calendario_escolar')
            ->where('id', $id)
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Calendario no encontrado'], 404);
        }

        return response()->json($row);
    }
}
