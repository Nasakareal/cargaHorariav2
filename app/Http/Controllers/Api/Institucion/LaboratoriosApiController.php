<?php

namespace App\Http\Controllers\Api\Institucion;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class LaboratoriosApiController extends Controller
{
    public function index()
    {
        $rows = DB::table('labs')
            ->orderBy('lab_name')
            ->get();
        return response()->json($rows);
    }

    public function show($id)
    {
        $row = DB::table('labs')
            ->where('lab_id', $id)
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Laboratorio no encontrado'], 404);
        }

        return response()->json($row);
    }
}
