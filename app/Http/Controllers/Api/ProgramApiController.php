<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgramApiController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');

        $rows = DB::table('programs as p')
            ->when($q, fn($qq) => $qq->where('p.program_name', 'like', "%{$q}%"))
            ->select('p.program_id as id', 'p.program_name as nombre')
            ->orderBy('p.program_name')
            ->paginate(50);

        return response()->json($rows);
    }

    public function show($id)
    {
        $row = DB::table('programs')
            ->where('program_id', $id)
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Programa no encontrado'], 404);
        }

        return response()->json($row);
    }
}
