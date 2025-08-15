<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubjectApiController extends Controller
{
    public function index(Request $request)
    {
        $q           = $request->query('q');
        $programId   = $request->query('programa_id');

        $rows = DB::table('subjects as s')
            ->when($q, fn($qq) => $qq->where('s.subject_name', 'like', "%{$q}%"))
            ->when($programId, fn($qq) => $qq->where('s.program_id', $programId))
            ->select(
                's.subject_id as id',
                's.subject_name as nombre',
                's.program_id'
            )
            ->orderBy('s.subject_name')
            ->paginate(50);

        return response()->json($rows);
    }

    public function show($id)
    {
        $row = DB::table('subjects')
            ->where('subject_id', $id)
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Materia no encontrada'], 404);
        }

        return response()->json($row);
    }
}
