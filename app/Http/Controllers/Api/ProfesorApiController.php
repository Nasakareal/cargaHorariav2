<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Models\ActividadGeneral;

class ProfesorApiController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');

        $rows = DB::table('teachers as t')
            ->when($q, fn($qq) => $qq->where('t.teacher_name', 'like', "%{$q}%"))
            ->select([
                't.teacher_id as id',
                't.teacher_name as nombre',
                't.programa_adscripcion',
                't.clasificacion',
                't.hours',
                't.estado',
                't.fyh_creacion',
                't.fyh_actualizacion',
            ])
            ->orderBy('t.teacher_name')
            ->paginate(50);

        return response()->json($rows);
    }

    public function show($id)
    {
        $row = DB::table('teachers')
            ->where('teacher_id', (int)$id)
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Profesor no encontrado'], 404);
        }

        // Agrega un pequeño resumen (materias/grupos/horas) útil para Flutter
        $agg = DB::table('teacher_subjects as ts')
            ->leftJoin('subjects as s', 's.subject_id', '=', 'ts.subject_id')
            ->leftJoin('groups as g',   'g.group_id',   '=', 'ts.group_id')
            ->leftJoin('programs as p', 'p.program_id', '=', 'g.program_id')
            ->where('ts.teacher_id', (int)$id)
            ->selectRaw('GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.subject_name SEPARATOR ", ") AS materias')
            ->selectRaw('GROUP_CONCAT(DISTINCT p.program_name ORDER BY p.program_name SEPARATOR ", ") AS programas')
            ->selectRaw('GROUP_CONCAT(DISTINCT g.group_name   ORDER BY g.group_name   SEPARATOR ", ") AS grupos')
            ->selectRaw('COALESCE(SUM(s.weekly_hours),0) AS horas_semanales')
            ->first();

        return response()->json([
            'profesor' => $row,
            'resumen'  => [
                'materias'        => $agg->materias ?? null,
                'programas'       => $agg->programas ?? null,
                'grupos'          => $agg->grupos ?? null,
                'horas_semanales' => (int)($agg->horas_semanales ?? 0),
            ],
        ]);
    }

    public function store(Request $request)
    {
        if (!Auth::user() || !Auth::user()->can('crear profesores')) {
            return response()->json(['message' => 'Sin permiso para crear profesores'], 403);
        }

        $request->validate([
            'teacher_name' => 'required|string|max:255',
            'clasificacion'=> 'nullable|in:PTC,PA,TA',
            'programa_adscripcion' => 'nullable|string|max:255',
        ], [
            'teacher_name.required' => 'El nombre del profesor es obligatorio.',
        ]);

        try {
            $id = DB::table('teachers')->insertGetId([
                'teacher_name'      => $request->teacher_name,
                'clasificacion'     => $request->clasificacion,
                'programa_adscripcion' => $request->programa_adscripcion,
                'hours'             => 0,
                'estado'            => 'ACTIVO',
                'fyh_creacion'      => now(),
                'fyh_actualizacion' => null,
            ], 'teacher_id');

            ActividadGeneral::registrar('CREAR', 'teachers', $id, "Creó al profesor {$request->teacher_name}");

            $row = DB::table('teachers')->where('teacher_id', $id)->first();
            return response()->json($row, 201);
        } catch (QueryException $e) {
            Log::error('Error BD al crear profesor', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return response()->json(['message'=>'Error de base de datos al crear el profesor'], 500);
        } catch (\Throwable $e) {
            Log::error('Error inesperado al crear profesor', ['msg'=>$e->getMessage()]);
            return response()->json(['message'=>'Ocurrió un error inesperado al crear el profesor'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        if (!Auth::user() || !Auth::user()->can('editar profesores')) {
            return response()->json(['message' => 'Sin permiso para editar profesores'], 403);
        }

        $profesor = DB::table('teachers')->where('teacher_id', (int)$id)->first();
        if (!$profesor) {
            return response()->json(['message' => 'Profesor no encontrado'], 404);
        }

        $request->validate([
            'teacher_name' => 'required|string|max:255',
            'clasificacion'=> 'required|in:PTC,PA,TA',
            'programa_adscripcion' => 'nullable|string|max:255',
        ], [
            'teacher_name.required' => 'El nombre del profesor es obligatorio.',
            'clasificacion.required'=> 'La clasificación es obligatoria.',
        ]);

        try {
            DB::table('teachers')
                ->where('teacher_id', (int)$id)
                ->update([
                    'teacher_name'      => $request->teacher_name,
                    'clasificacion'     => $request->clasificacion,
                    'programa_adscripcion' => $request->programa_adscripcion,
                    'fyh_actualizacion' => now(),
                ]);

            ActividadGeneral::registrar('ACTUALIZAR', 'teachers', (int)$id, "Actualizó al profesor {$request->teacher_name}");

            $row = DB::table('teachers')->where('teacher_id', (int)$id)->first();
            return response()->json($row);
        } catch (QueryException $e) {
            Log::error('Error BD al actualizar profesor', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return response()->json(['message'=>'Error de base de datos al actualizar el profesor'], 500);
        } catch (\Throwable $e) {
            Log::error('Error inesperado al actualizar profesor', ['msg'=>$e->getMessage()]);
            return response()->json(['message'=>'Ocurrió un error inesperado al actualizar el profesor'], 500);
        }
    }

    public function destroy($id)
    {
        if (!Auth::user() || !Auth::user()->can('eliminar profesores')) {
            return response()->json(['message' => 'Sin permiso para eliminar profesores'], 403);
        }

        $profesor = DB::table('teachers')->where('teacher_id', (int)$id)->first();
        if (!$profesor) {
            return response()->json(['message' => 'Profesor no encontrado'], 404);
        }

        $tieneMaterias = DB::table('teacher_subjects')->where('teacher_id', (int)$id)->exists();
        if ($tieneMaterias) {
            return response()->json(['message' => 'No se puede eliminar un profesor con materias asignadas'], 422);
        }

        try {
            ActividadGeneral::registrar('ELIMINAR', 'teachers', (int)$id, "Eliminó al profesor {$profesor->teacher_name}");
            DB::table('teachers')->where('teacher_id', (int)$id)->delete();
            return response()->json(['ok' => true]);
        } catch (QueryException $e) {
            Log::error('Error BD al eliminar profesor', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return response()->json(['message'=>'Error de base de datos al eliminar el profesor'], 500);
        } catch (\Throwable $e) {
            Log::error('Error inesperado al eliminar profesor', ['msg'=>$e->getMessage()]);
            return response()->json(['message'=>'Ocurrió un error inesperado al eliminar el profesor'], 500);
        }
    }
}
