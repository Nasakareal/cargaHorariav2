<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\ActividadGeneral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    # ===================== INDEX =====================
    public function index()
    {
        // Agregados por materia desde la tabla puente
        $aggProg = DB::table('program_term_subjects as pts')
            ->join('programs as p', 'p.program_id', '=', 'pts.program_id')
            ->select(
                'pts.subject_id',
                DB::raw('GROUP_CONCAT(DISTINCT p.program_name ORDER BY p.program_name SEPARATOR ", ") AS programas')
            )
            ->groupBy('pts.subject_id');

        $aggTerm = DB::table('program_term_subjects as pts')
            ->join('terms as t', 't.term_id', '=', 'pts.term_id')
            ->select(
                'pts.subject_id',
                DB::raw('GROUP_CONCAT(DISTINCT t.term_name ORDER BY t.term_name SEPARATOR ", ") AS cuatrimestres')
            )
            ->groupBy('pts.subject_id');

        // Join a los agregados + fallback a subjects.program_id / subjects.term_id
        $materias = DB::table('subjects as s')
            ->leftJoinSub($aggProg, 'ap', 'ap.subject_id', '=', 's.subject_id')
            ->leftJoinSub($aggTerm, 'at', 'at.subject_id', '=', 's.subject_id')
            ->leftJoin('programs as pdir', 'pdir.program_id', '=', 's.program_id')
            ->leftJoin('terms as tdir', 'tdir.term_id', '=', 's.term_id')
            ->select([
                's.subject_id',
                's.subject_name',
                's.weekly_hours',
                's.max_consecutive_class_hours',
                's.unidades',
                's.fyh_creacion',
                DB::raw('COALESCE(NULLIF(ap.programas, ""), pdir.program_name)  AS programas'),
                DB::raw('COALESCE(NULLIF(at.cuatrimestres, ""), tdir.term_name) AS cuatrimestres'),
            ])
            ->orderBy('s.subject_name')
            ->get();

        return view('materias.index', compact('materias'));
    }

    # ===================== CREATE ====================
    public function create()
    {
        // catálogos para armar las relaciones programa–cuatrimestre
        $programas = DB::table('programs')->orderBy('program_name')->get(['program_id','program_name']);
        $terms     = DB::table('terms')->orderBy('term_id')->get(['term_id','term_name']);

        return view('materias.create', compact('programas','terms'));
    }

    # ===================== STORE =====================
    public function store(Request $request)
    {
        $data = $request->validate([
            'subject_name' => [
                'required', 'string', 'max:255',
                // Único por (programa, cuatrimestre)
                Rule::unique('subjects', 'subject_name')
                    ->where(fn($q) => $q->where('program_id', $request->program_id)
                                       ->where('term_id',    $request->term_id)),
            ],
            'weekly_hours'                => 'required|integer|min:1',
            'max_consecutive_class_hours' => 'required|integer|min:1',
            'program_id'                  => 'required|integer|exists:programs,program_id',
            'term_id'                     => 'required|integer|exists:terms,term_id',
            'unidades'                    => 'required|integer|min:1',
            'estado'                      => 'nullable|in:ACTIVO,INACTIVO',
        ], [
            'subject_name.required' => 'El nombre de la materia es obligatorio.',
            'subject_name.unique'   => 'Ya existe una materia con ese nombre en el mismo programa y cuatrimestre.',
            'weekly_hours.required' => 'Las horas semanales son obligatorias.',
            'program_id.required'   => 'El programa es obligatorio.',
            'term_id.required'      => 'El cuatrimestre es obligatorio.',
            'unidades.required'     => 'Las unidades son obligatorias.',
        ]);

        // Normaliza espacios en el nombre
        $data['subject_name'] = preg_replace('/\s+/', ' ', trim($data['subject_name']));
        $data['estado']       = $data['estado'] ?? 'ACTIVO';

        try {
            DB::beginTransaction();

            $materia = Subject::create([
                'subject_name'                => $data['subject_name'],
                'weekly_hours'                => (int) $data['weekly_hours'],
                'max_consecutive_class_hours' => (int) $data['max_consecutive_class_hours'],
                'program_id'                  => (int) $data['program_id'],
                'term_id'                     => (int) $data['term_id'],
                'unidades'                    => (int) $data['unidades'],
                'estado'                      => $data['estado'],
            ]);

            ActividadGeneral::registrar('CREAR', 'subjects', $materia->subject_id, "Creó materia {$materia->subject_name}");

            DB::commit();
            return redirect()->route('materias.index')->with('success', 'Materia creada correctamente.');
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            Log::error('Error BD al crear materia', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Error de base de datos al crear la materia.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error inesperado al crear materia', ['msg' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Ocurrió un error inesperado al crear la materia.');
        }
    }

    # ====================== SHOW ======================
public function show($id)
{
    $materia = DB::table('subjects as s')
        ->leftJoin('programs as p', 'p.program_id', '=', 's.program_id')   // por si guardas principal
        ->leftJoin('terms as t',    't.term_id',    '=', 's.term_id')
        ->where('s.subject_id', (int)$id)
        ->select('s.*','p.program_name','t.term_name')
        ->first();

    if (!$materia) {
        return redirect()->route('materias.index')->with('error','La materia no existe.');
    }

    // Relaciones N–N desde program_term_subjects
    $rel = DB::table('program_term_subjects as pts')
        ->join('programs as p', 'p.program_id', '=', 'pts.program_id')
        ->join('terms as t',    't.term_id',    '=', 'pts.term_id')
        ->where('pts.subject_id', (int)$id)
        ->orderBy('p.program_name')
        ->orderBy('t.term_id')
        ->get(['p.program_name','t.term_name']);

    return view('materias.show', compact('materia','rel'));
}

    # ====================== EDIT ======================
    public function edit($id)
    {
        $materia = Subject::where('subject_id', (int)$id)->first();
        if (!$materia) {
            return redirect()->route('materias.index')->with('error','La materia no existe.');
        }

        $programas = DB::table('programs')
            ->orderBy('program_name')
            ->get(['program_id','program_name']);

        $terms = DB::table('terms')
            ->orderBy('term_id')
            ->get(['term_id','term_name']);

        return view('materias.edit', compact('materia','programas','terms'));
    }

    # ===================== UPDATE =====================
    public function update(Request $request, $id)
    {
        $materia = Subject::where('subject_id', (int)$id)->first();
        if (!$materia) {
            return redirect()->route('materias.index')->with('error', 'La materia no existe.');
        }

        // Validación: único por (nombre, programa, cuatrimestre)
        $data = $request->validate([
            'subject_name' => [
                'required', 'string', 'max:255',
                Rule::unique('subjects', 'subject_name')
                    ->where(fn($q) => $q->where('program_id', $request->program_id)
                                        ->where('term_id',    $request->term_id))
                    ->ignore($materia->subject_id, 'subject_id'),
            ],
            'weekly_hours'                => 'required|integer|min:1',
            'max_consecutive_class_hours' => 'required|integer|min:1',
            'program_id'                  => 'required|integer|exists:programs,program_id',
            'term_id'                     => 'required|integer|exists:terms,term_id',
            'unidades'                    => 'required|integer|min:1',
            'estado'                      => 'required|in:ACTIVO,INACTIVO',
        ], [
            'subject_name.required' => 'El nombre de la materia es obligatorio.',
            'subject_name.unique'   => 'Ya existe una materia con ese nombre en el mismo programa y cuatrimestre.',
            'weekly_hours.required' => 'Las horas semanales son obligatorias.',
            'program_id.required'   => 'El programa es obligatorio.',
            'term_id.required'      => 'El cuatrimestre es obligatorio.',
            'unidades.required'     => 'Las unidades son obligatorias.',
        ]);

        // Normaliza el nombre
        $data['subject_name'] = preg_replace('/\s+/', ' ', trim($data['subject_name']));

        try {
            DB::beginTransaction();

            $materia->update([
                'subject_name'                => $data['subject_name'],
                'weekly_hours'                => (int) $data['weekly_hours'],
                'max_consecutive_class_hours' => (int) $data['max_consecutive_class_hours'],
                'program_id'                  => (int) $data['program_id'],
                'term_id'                     => (int) $data['term_id'],
                'unidades'                    => (int) $data['unidades'],
                'estado'                      => $data['estado'],
            ]);

            ActividadGeneral::registrar(
                'ACTUALIZAR',
                'subjects',
                $materia->subject_id,
                "Actualizó materia {$materia->subject_name}"
            );

            DB::commit();
            return redirect()->route('materias.index')->with('success', 'Materia actualizada correctamente.');
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            Log::error('Error BD al actualizar materia', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return back()->withInput()->with('error', 'Error de base de datos al actualizar la materia.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error inesperado al actualizar materia', ['msg'=>$e->getMessage()]);
            return back()->withInput()->with('error', 'Ocurrió un error inesperado al actualizar la materia.');
        }
    }

    # ===================== DESTROY ====================
    public function destroy($id)
    {
        $materia = Subject::where('subject_id', (int)$id)->first();
        if (!$materia) {
            return redirect()->route('materias.index')->with('error','La materia no existe.');
        }

        if (!Auth::user()->can('eliminar materias')) {
            return redirect()->route('materias.index')->with('error','No tienes permiso para eliminar materias.');
        }

        // No permitir borrar si está en uso
        $enUso = DB::table('teacher_subjects')->where('subject_id', $materia->subject_id)->exists()
              || DB::table('schedule_assignments')->where('subject_id', $materia->subject_id)->exists()
              || DB::table('manual_schedule_assignments')->where('subject_id', $materia->subject_id)->exists();

        if ($enUso) {
            return redirect()->route('materias.index')->with('error','No se puede eliminar una materia que ya está asignada.');
        }

        try {
            DB::beginTransaction();

            DB::table('program_term_subjects')->where('subject_id', $materia->subject_id)->delete();
            $materia->delete();

            ActividadGeneral::registrar('ELIMINAR', 'subjects', $id, "Eliminó materia {$materia->subject_name}");

            DB::commit();
            return redirect()->route('materias.index')->with('success','Materia eliminada correctamente.');
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Error BD al eliminar materia', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return redirect()->route('materias.index')->with('error','Error de base de datos al eliminar la materia.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error inesperado al eliminar materia', ['msg'=>$e->getMessage()]);
            return redirect()->route('materias.index')->with('error','Ocurrió un error inesperado al eliminar la materia.');
        }
    }

    # ------------------- Helpers -------------------
    /**
     * Acepta cualquiera de estas formas desde el formulario:
     *  A) relaciones[] = ["<program_id>|<term_id>", ...]
     *  B) program_id[] y term_id[] (misma longitud): se interpretan como pares (p[i], t[i]).
     */
    private function parsePairsFromRequest(Request $request): array
    {
        $pairs = [];

        // A) relaciones = ["3|1","5|2",...]
        if (is_array($request->relaciones) && count($request->relaciones)) {
            foreach ($request->relaciones as $pair) {
                [$p,$t] = array_pad(explode('|', (string)$pair, 2), 2, null);
                if ($p && $t) $pairs[] = [(int)$p, (int)$t];
            }
            return $pairs;
        }

        // B) program_id[] + term_id[] con misma longitud
        $prog = (array) $request->program_id;
        $term = (array) $request->term_id;
        if (count($prog) === count($term)) {
            for ($i=0; $i<count($prog); $i++) {
                if ($prog[$i] && $term[$i]) $pairs[] = [(int)$prog[$i], (int)$term[$i]];
            }
        }

        return $pairs;
    }
}
