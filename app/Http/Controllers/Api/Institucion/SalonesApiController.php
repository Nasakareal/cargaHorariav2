<?php

namespace App\Http\Controllers\Api\Institucion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalonesApiController extends Controller
{
    public function index(Request $request)
    {
        $building = $request->query('building');
        $floor    = $request->query('floor');

        $rows = DB::table('classrooms')
            ->when($building, fn($q) => $q->where('building', $building))
            ->when($floor, fn($q) => $q->where('floor', strtoupper($floor)))
            ->orderByRaw('CAST(classroom_name AS UNSIGNED), classroom_name')
            ->get();

        return response()->json($rows);
    }

    public function show($id)
    {
        $row = DB::table('classrooms')
            ->where('classroom_id', $id)
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Salón no encontrado'], 404);
        }

        return response()->json($row);
    }
}
