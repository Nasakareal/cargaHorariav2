<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfesorApiController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');

        $rows = DB::table('teachers as t')
            ->when($q, fn($qq) => $qq->where('t.teacher_name', 'like', "%{$q}%"))
            ->select(
                't.teacher_id as id',
                't.teacher_name as nombre',
                't.programa_adscripcion',
            )
            ->orderBy('t.teacher_name')
            ->paginate(50);

        return response()->json($rows);
    }

    public function show($id)
    {
        $row = DB::table('teachers')
            ->where('teacher_id', $id)
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Profesor no encontrado'], 404);
        }

        return response()->json($row);
    }
}
