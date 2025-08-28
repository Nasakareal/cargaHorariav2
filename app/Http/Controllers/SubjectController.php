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
        $progSub = DB::table('program_term_subjects')
            ->select('subject_id', DB::raw('MIN(program_id) AS program_id'))
            ->groupBy('subject_id');

        $termSub = DB::table('program_term_subjects')
            ->select('subject_id', DB::raw('MIN(term_id) AS term_id'))
            ->groupBy('subject_id');

        $materias = DB::table('subjects as s')
            ->leftJoinSub($progSub, 'pp', 'pp.subject_id', '=', 's.subject_id')
            ->leftJoin('programs as p_dir', 'p_dir.program_id', '=', 's.program_id')
            ->leftJoin('programs as p_puente', function ($j) {
                $j->on('p_puente.program_id', '=', 'pp.program_id');
            })
            ->leftJoinSub($termSub, 'tt', 'tt.subject_id', '=', 's.subject_id')
            ->leftJoin('terms as t_dir', 't_dir.term_id', '=', 's.term_id')
            ->leftJoin('terms as t_puente', function ($j) {
                $j->on('t_puente.term_id', '=', 'tt.term_id');
            })
            ->select([
                's.subject_id',
                's.subject_name',
                's.weekly_hours',
                's.max_consecutive_class_hours',
                's.unidades',
                's.fyh_creacion',
                DB::raw('COALESCE(p_dir.program_name, p_puente.program_name) AS programas'),
                DB::raw('COALESCE(t_dir.term_name, t_puente.term_name) AS cuatrimestres'),
            ])
            ->orderBy('s.subject_name')
            ->get();

        $programas = DB::table('programs')
            ->orderBy('program_name')
            ->get(['program_id','program_name']);

        return view('materias.index', compact('materias','programas'));
    }

    # ===================== CREATE ====================
    public function create()
    {
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
                Rule::unique('subjects', 'subject_name')
                    ->where(fn($q) => $q->where('program_id', $request->program_id)
                                       ->where('term_id',    $request->term_id)),
            ],
            'weekly_hours'                => 'required|integer|min:1',
            'max_consecutive_class_hours' => 'required|integer|min:1',
            'program_id'                  => 'required|integer|exists:programs,program_id',
            'term_id'                     => 'required|integer|exists:terms,term_id',
            'unidades'                    => 'required|integer|min:1',
        ], [
            'subject_name.required' => 'El nombre de la materia es obligatorio.',
            'subject_name.unique'   => 'Ya existe una materia con ese nombre en el mismo programa y cuatrimestre.',
            'weekly_hours.required' => 'Las horas semanales son obligatorias.',
            'program_id.required'   => 'El programa es obligatorio.',
            'term_id.required'      => 'El cuatrimestre es obligatorio.',
            'unidades.required'     => 'Las unidades son obligatorias.',
        ]);

        $data['subject_name'] = preg_replace('/\s+/', ' ', trim($data['subject_name']));

        try {
            DB::beginTransaction();

            $materia = Subject::create([
                'subject_name'                => $data['subject_name'],
                'weekly_hours'                => (int) $data['weekly_hours'],
                'max_consecutive_class_hours' => (int) $data['max_consecutive_class_hours'],
                'program_id'                  => (int) $data['program_id'],
                'term_id'                     => (int) $data['term_id'],
                'unidades'                    => (int) $data['unidades'],
                'estado'                      => '1',
            ]);

            DB::table('program_term_subjects')->updateOrInsert(
                [
                    'program_id' => (int) $data['program_id'],
                    'term_id'    => (int) $data['term_id'],
                    'subject_id' => (int) $materia->subject_id,
                ],
                []
            );

            $this->vincularMateriaAGrupos((int)$materia->subject_id);

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
            ->leftJoin('programs as p', 'p.program_id', '=', 's.program_id')
            ->leftJoin('terms as t',    't.term_id',    '=', 's.term_id')
            ->where('s.subject_id', (int)$id)
            ->select('s.*','p.program_name','t.term_name')
            ->first();

        if (!$materia) {
            return redirect()->route('materias.index')->with('error','La materia no existe.');
        }

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
        ], [
            'subject_name.required' => 'El nombre de la materia es obligatorio.',
            'subject_name.unique'   => 'Ya existe una materia con ese nombre en el mismo programa y cuatrimestre.',
            'weekly_hours.required' => 'Las horas semanales son obligatorias.',
            'program_id.required'   => 'El programa es obligatorio.',
            'term_id.required'      => 'El cuatrimestre es obligatorio.',
            'unidades.required'     => 'Las unidades son obligatorias.',
        ]);

        $data['subject_name'] = preg_replace('/\s+/', ' ', trim($data['subject_name']));

        try {
            DB::beginTransaction();

            $oldProgramId = (int) $materia->program_id;
            $newProgramId = (int) $data['program_id'];
            $materia->update([
                'subject_name'                => $data['subject_name'],
                'weekly_hours'                => (int) $data['weekly_hours'],
                'max_consecutive_class_hours' => (int) $data['max_consecutive_class_hours'],
                'program_id'                  => $newProgramId,
                'term_id'                     => (int) $data['term_id'],
                'unidades'                    => (int) $data['unidades'],
                'estado'                      => '1',
            ]);

            if ($oldProgramId !== $newProgramId) {
                DB::table('program_term_subjects as pts_old')
                  ->where('pts_old.subject_id', $materia->subject_id)
                  ->where('pts_old.program_id', $oldProgramId)
                  ->whereExists(function ($q) use ($materia, $newProgramId) {
                      $q->select(DB::raw(1))
                        ->from('program_term_subjects as pts_new')
                        ->whereColumn('pts_new.subject_id', 'pts_old.subject_id')
                        ->whereColumn('pts_new.term_id', 'pts_old.term_id')
                        ->where('pts_new.program_id', $newProgramId);
                  })
                  ->delete();

                DB::table('program_term_subjects')
                  ->where('subject_id', $materia->subject_id)
                  ->where('program_id', $oldProgramId)
                  ->update(['program_id' => $newProgramId]);
            }

            DB::table('program_term_subjects')->updateOrInsert(
                [
                    'subject_id' => (int) $materia->subject_id,
                    'term_id'    => (int) $data['term_id'],
                    'program_id' => $newProgramId,
                ],
                []
            );

            $this->vincularMateriaAGrupos((int)$materia->subject_id);

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

    # ===================== DESTROY =====================
    public function destroy($id)
    {
        $materia = Subject::where('subject_id', (int)$id)->first();
        if (!$materia) {
            return redirect()->route('materias.index')->with('error','La materia no existe.');
        }

        if (!Auth::user()->can('eliminar materias')) {
            return redirect()->route('materias.index')->with('error','No tienes permiso para eliminar materias.');
        }

        $conn   = $materia->getConnectionName() ?: config('database.default');
        $dbName = DB::connection($conn)->getDatabaseName();

        try {
            DB::connection($conn)->beginTransaction();
            DB::connection($conn)->statement('SET FOREIGN_KEY_CHECKS=0');

            // 1) Identifica TODAS las tablas que referencian subjects(subject_id) y borra sus filas
            $fks = DB::connection($conn)->select("
                SELECT TABLE_NAME, COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_SCHEMA = ?
                  AND REFERENCED_TABLE_NAME   = 'subjects'
                  AND REFERENCED_COLUMN_NAME  = 'subject_id'
            ", [$dbName]);

            foreach ($fks as $fk) {
                if (strcasecmp($fk->TABLE_NAME, 'subjects') === 0) { continue; }
                DB::connection($conn)
                    ->table($fk->TABLE_NAME)
                    ->where($fk->COLUMN_NAME, $materia->subject_id)
                    ->delete();
            }

            // 2) Tablas conocidas (por si alguna no tuviera FK formal o nombres raros)
            $extras = [
                'program_term_subjects',
                'teacher_subjects',
                'schedule_assignments',
                'manual_schedule_assignments',
                'subject_labs',
            ];
            foreach ($extras as $tbl) {
                try {
                    DB::connection($conn)->table($tbl)->where('subject_id', $materia->subject_id)->delete();
                } catch (\Throwable $e) {
                }
            }

            // 3) Borra la materia
            DB::connection($conn)
                ->table('subjects')
                ->where('subject_id', $materia->subject_id)
                ->delete();

            // 4) Bitácora
            ActividadGeneral::registrar('ELIMINAR', 'subjects', (int)$id, "Eliminó materia {$materia->subject_name}");

            // 🔒 Reactiva FK checks y cierra tx
            DB::connection($conn)->statement('SET FOREIGN_KEY_CHECKS=1');
            DB::connection($conn)->commit();

            return redirect()->route('materias.index')->with('success','Materia eliminada correctamente.');

        } catch (\Throwable $e) {
            try { DB::connection($conn)->statement('SET FOREIGN_KEY_CHECKS=1'); } catch (\Throwable $e2) {}
            DB::connection($conn)->rollBack();
            \Log::error('Error al eliminar materia (forzado)', [
                'subject_id' => (int)$id,
                'msg'        => $e->getMessage()
            ]);
            return redirect()->route('materias.index')->with('error', 'No se pudo eliminar: '.$e->getMessage());
        }
    }

    # ------------------- Helpers -------------------
    private function parsePairsFromRequest(Request $request): array
    {
        $pairs = [];

        if (is_array($request->relaciones) && count($request->relaciones)) {
            foreach ($request->relaciones as $pair) {
                [$p,$t] = array_pad(explode('|', (string)$pair, 2), 2, null);
                if ($p && $t) $pairs[] = [(int)$p, (int)$t];
            }
            return $pairs;
        }

        $prog = (array) $request->program_id;
        $term = (array) $request->term_id;
        if (count($prog) === count($term)) {
            for ($i=0; $i<count($prog); $i++) {
                if ($prog[$i] && $term[$i]) $pairs[] = [(int)$prog[$i], (int)$term[$i]];
            }
        }

        return $pairs;
    }

    private function vincularMateriaAGrupos(int $subjectId): void
    {
        // 1) Pairs por pivot
        $pairs = DB::table('program_term_subjects')
            ->where('subject_id', $subjectId)
            ->select('program_id','term_id')
            ->distinct()
            ->get();

        // 2) Si no hay en pivot, usar el programa/term directo de subjects
        if ($pairs->isEmpty()) {
            $row = DB::table('subjects')
                ->where('subject_id', $subjectId)
                ->first(['program_id','term_id']);
            if ($row && $row->program_id && $row->term_id) {
                $pairs = collect([(object)['program_id'=>$row->program_id, 'term_id'=>$row->term_id]]);
            }
        }

        // 3) Insertar faltantes en group_subjects (uno por cada grupo que encaje)
        foreach ($pairs as $p) {
            DB::statement('
                INSERT INTO group_subjects (group_id, subject_id, fyh_creacion, fyh_actualizacion, estado)
                SELECT g.group_id, ?, NOW(), NULL, 1
                FROM groups g
                LEFT JOIN group_subjects gs
                       ON gs.group_id = g.group_id
                      AND gs.subject_id = ?
                WHERE g.program_id = ?
                  AND g.term_id    = ?
                  AND gs.group_subject_id IS NULL
            ', [
                $subjectId,
                $subjectId,
                (int)$p->program_id,
                (int)$p->term_id,
            ]);
        }
    }

}
