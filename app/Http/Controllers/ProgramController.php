<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use App\Models\ActividadGeneral;

class ProgramController extends Controller
{
    /* ===================== INDEX SIMPLE ===================== */
    public function index()
    {
        $programas = DB::table('programs as p')
            ->leftJoin('groups as g', 'g.program_id', '=', 'p.program_id')
            ->leftJoin('program_term_subjects as pts', 'pts.program_id', '=', 'p.program_id')
            ->select([
                'p.program_id',
                'p.program_name',
                'p.area',
                DB::raw('COUNT(DISTINCT g.group_id) AS total_grupos'),
                DB::raw('COUNT(DISTINCT pts.subject_id) AS total_materias'),
            ])
            ->groupBy('p.program_id', 'p.program_name', 'p.area')
            ->orderBy('p.program_name')
            ->get();

        return view('programas.index', compact('programas'));
    }

    /* ========== INDEX CON MATERIAS (agregadas por programa) ========== */
    public function indexWithSubjects()
    {
        $programas = DB::table('programs as p')
            ->leftJoin('program_term_subjects as pts', 'pts.program_id', '=', 'p.program_id')
            ->leftJoin('subjects as s', 's.subject_id', '=', 'pts.subject_id')
            ->leftJoin('terms as t', 't.term_id', '=', 'pts.term_id')
            ->select([
                'p.program_id',
                'p.program_name',
                'p.area',
                DB::raw('COUNT(DISTINCT pts.subject_id) AS total_materias'),
                DB::raw('GROUP_CONCAT(DISTINCT t.term_name ORDER BY t.term_name SEPARATOR ", ") AS cuatrimestres'),
                DB::raw('GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.subject_name SEPARATOR ", ") AS materias'),
            ])
            ->groupBy('p.program_id', 'p.program_name', 'p.area')
            ->orderBy('p.program_name')
            ->get();

        return view('programas.index_materias', compact('programas'));
    }

    public function create() {
        $areas = DB::table('programs')
            ->whereNotNull('area')
            ->select('area')->distinct()->orderBy('area')->pluck('area')->toArray();
        return view('programas.create', compact('areas'));
    }

    public function store(Request $request) {
        $request->validate([
            'program_name' => 'required|string|max:255',
            'area'         => 'nullable|string|max:255',
        ], [
            'program_name.required' => 'El nombre del programa es obligatorio.',
        ]);

        DB::table('programs')->insert([
            'program_name'    => $request->program_name,
            'area'            => $request->area,
            'fyh_creacion'    => now(),
            'fyh_actualizacion'=> null,
            'estado'          => '1',
        ]);

        \App\Models\ActividadGeneral::registrar('CREAR', 'programs', null, "Creó el programa {$request->program_name}");

        return redirect()->route('programas.index')->with('success','Programa creado correctamente.');
    }

    /* ========== (Opcional) Solo materias de un programa ========== */
    public function subjects($id)
    {
        $id = (int) $id;

        $programa = DB::table('programs')->where('program_id', $id)->first();
        if (!$programa) {
            return redirect()->route('programas.index')
                ->with('error', 'El programa no existe o ya fue eliminado.');
        }

        $materias = DB::table('program_term_subjects as pts')
            ->join('subjects as s', 's.subject_id', '=', 'pts.subject_id')
            ->leftJoin('terms as t', 't.term_id', '=', 'pts.term_id')
            ->where('pts.program_id', $id)
            ->orderBy('t.term_name')
            ->orderBy('s.subject_name')
            ->get([
                's.subject_id',
                's.subject_name',
                's.weekly_hours',
                't.term_name as cuatrimestre',
            ]);

        return view('programas.subjects', compact('programa', 'materias'));
    }

    /* ===================== SHOW ===================== */
    public function show($id)
    {
        $id = (int) $id;

        $programa = DB::table('programs')->where('program_id', $id)->first();
        if (!$programa) {
            return redirect()->route('programas.index')
                ->with('error', 'El programa no existe o ya fue eliminado.');
        }

        // Agregados del programa
        $agg = DB::table('program_term_subjects as pts')
            ->leftJoin('subjects as s', 's.subject_id', '=', 'pts.subject_id')
            ->leftJoin('terms as t', 't.term_id', '=', 'pts.term_id')
            ->where('pts.program_id', $id)
            ->selectRaw('COUNT(DISTINCT pts.subject_id) AS total_materias')
            ->selectRaw('GROUP_CONCAT(DISTINCT t.term_name ORDER BY t.term_name SEPARATOR ", ") AS cuatrimestres')
            ->selectRaw('GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.subject_name SEPARATOR ", ") AS materias')
            ->first();

        // Grupos del programa
        $grupos = DB::table('groups')
            ->where('program_id', $id)
            ->orderBy('group_name')
            ->get(['group_id','group_name','term_id','turn_id']);

        // Materias detalladas (por cuatrimestre)
        $materias = DB::table('program_term_subjects as pts')
            ->join('subjects as s', 's.subject_id', '=', 'pts.subject_id')
            ->leftJoin('terms as t', 't.term_id', '=', 'pts.term_id')
            ->where('pts.program_id', $id)
            ->orderBy('t.term_name')
            ->orderBy('s.subject_name')
            ->get([
                's.subject_id','s.subject_name','s.weekly_hours',
                't.term_name as cuatrimestre'
            ]);

        return view('programas.show', [
            'programa'       => $programa,
            'total_materias' => (int)($agg->total_materias ?? 0),
            'cuatrimestres'  => $agg->cuatrimestres ?? null,
            'materias_join'  => $agg->materias ?? null,
            'materias'       => $materias,
            'grupos'         => $grupos,
        ]);
    }

    /* ===================== EDIT ===================== */
    public function edit($id)
    {
        $id = (int) $id;

        $programa = DB::table('programs')->where('program_id', $id)->first();
        if (!$programa) {
            return redirect()->route('programas.index')
                ->with('error', 'El programa no existe o ya fue eliminado.');
        }

        // Áreas distintas para tu select (si usas ese campo)
        $areas = DB::table('programs')
            ->whereNotNull('area')
            ->select('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area')
            ->toArray();

        return view('programas.edit', compact('programa','areas'));
    }

    /* ===================== UPDATE ===================== */
    public function update(Request $request, $id)
    {
        $id = (int) $id;

        $programa = DB::table('programs')->where('program_id', $id)->first();
        if (!$programa) {
            return redirect()->route('programas.index')
                ->with('error', 'El programa no existe o ya fue eliminado.');
        }

        $request->validate([
            'program_name' => 'required|string|max:255',
            'area'         => 'nullable|string|max:255',
        ], [
            'program_name.required' => 'El nombre del programa es obligatorio.',
        ]);

        try {
            DB::table('programs')
                ->where('program_id', $id)
                ->update([
                    'program_name'     => $request->program_name,
                    'area'             => $request->area,
                    'fyh_actualizacion'=> now(),
                ]);

            ActividadGeneral::registrar('ACTUALIZAR', 'programs', $id, "Actualizó el programa {$request->program_name}");

            return redirect()->route('programas.index', $id)
                ->with('success', 'Programa actualizado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al actualizar programa', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Error de base de datos al actualizar el programa.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al actualizar programa', ['msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Ocurrió un error inesperado al actualizar el programa.');
        }
    }

    /* ===================== DESTROY ===================== */
    public function destroy($id)
    {
        $id = (int) $id;

        $programa = DB::table('programs')->where('program_id', $id)->first();
        if (!$programa) {
            return redirect()->route('programas.index')
                ->with('error', 'El programa no existe o ya fue eliminado.');
        }

        if (!Auth::user()->can('eliminar programas')) {
            return redirect()->route('programas.index')
                ->with('error', 'No tienes permiso para eliminar programas.');
        }

        // Reglas: no eliminar si tiene grupos o materias relacionadas
        $tieneGrupos   = DB::table('groups')->where('program_id', $id)->exists();
        $tieneMaterias = DB::table('program_term_subjects')->where('program_id', $id)->exists();

        if ($tieneGrupos || $tieneMaterias) {
            return redirect()->route('programas.index')
                ->with('error', 'No se puede eliminar: este programa tiene grupos o materias relacionadas.');
        }

        try {
            ActividadGeneral::registrar('ELIMINAR', 'programs', $id, "Eliminó el programa {$programa->program_name}");
            DB::table('programs')->where('program_id', $id)->delete();

            return redirect()->route('programas.index')
                ->with('success', 'Programa eliminado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al eliminar programa', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return redirect()->route('programas.index')
                ->with('error', 'Error de base de datos al eliminar el programa.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al eliminar programa', ['msg'=>$e->getMessage()]);
            return redirect()->route('programas.index')
                ->with('error', 'Ocurrió un error inesperado al eliminar el programa.');
        }
    }
}
