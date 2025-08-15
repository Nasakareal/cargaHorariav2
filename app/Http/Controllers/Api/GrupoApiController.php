<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GrupoApiController extends Controller
{
    public function index(Request $request)
    {
        $q         = $request->query('q');
        $programId = $request->query('programa_id');
        $turno     = $request->query('turno');

        $rows = DB::table('groups as g')
            ->when($q, fn($qq) => $qq->where('g.group_name', 'like', "%{$q}%"))
            ->when($programId, fn($qq) => $qq->where('g.program_id', $programId))
            ->when($turno, fn($qq) => $qq->where('g.shift', $turno))
            ->select(
                'g.group_id as id',
                'g.group_name as nombre',
                'g.shift as turno',
                'g.program_id'
            )
            ->orderBy('g.group_name')
            ->paginate(50);

        return response()->json($rows);
    }

    public function show($id)
    {
        $row = DB::table('groups')
            ->where('group_id', $id)
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Grupo no encontrado'], 404);
        }

        return response()->json($row);
    }
}
