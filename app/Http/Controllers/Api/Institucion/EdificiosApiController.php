<?php

namespace App\Http\Controllers\Api\Institucion;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class EdificiosApiController extends Controller
{
    public function index()
    {
        $rows = DB::table('classrooms')
            ->select('building')
            ->distinct()
            ->orderBy('building')
            ->get();
        return response()->json($rows);
    }

    public function show($id)
    {
        $rows = DB::table('classrooms')
            ->where('building', $id)
            ->orderBy('classroom_name')
            ->get();
        return response()->json($rows);
    }

    public function plantas($building)
    {
        $floors = DB::table('classrooms')
            ->where('building', $building)
            ->whereNotNull('floor')
            ->select('floor')
            ->distinct()
            ->orderBy('floor')
            ->pluck('floor')
            ->map(fn($f) => strtoupper((string)$f))
            ->values();

        if ($floors->isEmpty()) {
            $floors = collect(['BAJA','ALTA']);
        }

        return response()->json($floors);
    }
}
