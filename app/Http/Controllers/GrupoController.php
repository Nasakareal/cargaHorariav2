<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Models\ActividadGeneral;

class GrupoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /* =================== INDEX =================== */
    public function index()
    {
        // Listado con agregados: total materias, asignadas y faltantes
        $grupos = DB::table('groups as g')
            ->leftJoin('programs as p', 'p.program_id', '=', 'g.program_id')
            ->leftJoin('terms as tm',   'tm.term_id',   '=', 'g.term_id')
            ->leftJoin('shifts as sh',  'sh.shift_id',  '=', 'g.turn_id')
            ->leftJoin('group_subjects as gs', 'gs.group_id', '=', 'g.group_id')
            ->leftJoin('teacher_subjects as ts', function ($j) {
                $j->on('ts.group_id', '=', 'g.group_id')
                  ->on('ts.subject_id', '=', 'gs.subject_id');
            })
            ->groupBy('g.group_id', 'g.group_name', 'g.area', 'g.volume', 'p.program_name', 'tm.term_name', 'sh.shift_name')
            ->select([
                'g.group_id',
                'g.group_name',
                'g.area',
                'g.volume',
                DB::raw('COALESCE(p.program_name,"—") as program_name'),
                DB::raw('COALESCE(tm.term_name,"—")   as term_name'),
                DB::raw('COALESCE(sh.shift_name,"—")  as shift_name'),
                DB::raw('COUNT(DISTINCT gs.subject_id) as total_materias'),
                DB::raw('COUNT(DISTINCT ts.subject_id) as materias_asignadas'),
                DB::raw('(COUNT(DISTINCT gs.subject_id) - COUNT(DISTINCT ts.subject_id)) as materias_no_cubiertas'),
            ])
            ->orderBy('p.program_name')
            ->orderByRaw('CAST(tm.term_name AS UNSIGNED), tm.term_name')
            ->orderBy('g.group_name')
            ->get();

        return view('grupos.index', compact('grupos'));
    }

    /* =================== CREATE =================== */
    public function create()
        {
            $programas = DB::table('programs')
                ->select('program_id','program_name')
                ->orderBy('program_name')->get();

            $terminos = DB::table('terms')
                ->select('term_id','term_name')
                ->orderBy('term_id')->get();

            $turnos = DB::table('shifts')
                ->select('shift_id','shift_name')
                ->orderBy('shift_id')->get();

            return view('grupos.create', compact('programas','terminos','turnos'));
        }

    /* =================== STORE =================== */
    public function store(Request $request)
    {
        $request->validate([
            'group_name' => 'required|string|max:100',
            'program_id' => 'required|integer|exists:programs,program_id',
            'term_id'    => 'required|integer|exists:terms,term_id',
            'turn_id'    => 'required|integer|exists:shifts,shift_id',
            'volume'     => 'nullable|integer|min:0',
            'classroom_assigned' => 'nullable|integer',
            'lab_assigned'       => 'nullable|integer',
        ], [], [
            'group_name' => 'nombre del grupo',
            'program_id' => 'programa',
            'term_id'    => 'cuatrimestre',
            'turn_id'    => 'turno',
        ]);

        try {
            $program = DB::table('programs')->where('program_id', $request->program_id)->first(['area']);

            $id = DB::table('groups')->insertGetId([
                'group_name'        => $request->group_name,
                'program_id'        => $request->program_id,
                'area'              => $program->area ?? null,
                'term_id'           => $request->term_id,
                'volume'            => $request->volume ?? 0,
                'turn_id'           => $request->turn_id,
                'classroom_assigned'=> $request->classroom_assigned,
                'lab_assigned'      => $request->lab_assigned,
                'fyh_creacion'      => now(),
                'fyh_actualizacion' => null,
                'estado'            => 1,
            ]);

            if (class_exists(ActividadGeneral::class)) {
                ActividadGeneral::registrar('CREAR', 'groups', $id, "Creó el grupo {$request->group_name}");
            }

            return redirect()->route('grupos.index')->with('success', 'Grupo creado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al crear grupo', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Error de base de datos al crear el grupo.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al crear grupo', ['msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Ocurrió un error inesperado al crear el grupo.');
        }
    }

    /* =================== SHOW =================== */
    public function show($id)
    {
        $g = DB::table('groups as g')
            ->leftJoin('programs as p', 'p.program_id', '=', 'g.program_id')
            ->leftJoin('terms as tm',   'tm.term_id',   '=', 'g.term_id')
            ->leftJoin('shifts as sh',  'sh.shift_id',  '=', 'g.turn_id')
            ->where('g.group_id', (int)$id)
            ->first([
                'g.group_id','g.group_name','g.area','g.volume',
                'g.classroom_assigned','g.lab_assigned',
                DB::raw('COALESCE(p.program_name,"—") as program_name'),
                DB::raw('COALESCE(tm.term_name,"—")   as term_name'),
                DB::raw('COALESCE(sh.shift_name,"—")  as shift_name'),
                'g.fyh_creacion','g.fyh_actualizacion','g.estado'
            ]);

        if (!$g) {
            return redirect()->route('grupos.index')->with('error', 'El grupo no existe o ya fue eliminado.');
        }

        $materias = DB::table('group_subjects as gs')
            ->join('subjects as s', 's.subject_id', '=', 'gs.subject_id')
            ->leftJoin('teacher_subjects as ts', function ($j) {
                $j->on('ts.group_id','=','gs.group_id')
                  ->on('ts.subject_id','=','gs.subject_id');
            })
            ->leftJoin('teachers as t', 't.teacher_id', '=', 'ts.teacher_id')
            ->where('gs.group_id', $g->group_id)
            ->orderBy('s.subject_name')
            ->select([
                's.subject_id',
                's.subject_name',
                's.weekly_hours',
                DB::raw('COALESCE(t.teacher_name,"Sin profesor") as teacher_name')
            ])
            ->get();

        $totalMaterias       = $materias->count();
        $materiasAsignadas   = $materias->where('teacher_name','!=','Sin profesor')->count();
        $materiasNoCubiertas = $totalMaterias - $materiasAsignadas;

        return view('grupos.show', [
            'grupo'                => $g,
            'materias'             => $materias,
            'total_materias'       => $totalMaterias,
            'materias_asignadas'   => $materiasAsignadas,
            'materias_no_cubiertas'=> $materiasNoCubiertas,
        ]);
    }

    /* =================== EDIT =================== */
    public function edit($id)
    {
        $grupo = DB::table('groups')->where('group_id', (int)$id)->first();
        if (!$grupo) {
            return redirect()->route('grupos.index')->with('error','El grupo no existe.');
        }

        $programas = DB::table('programs')->select('program_id','program_name')->orderBy('program_name')->get();
        $terminos  = DB::table('terms')->select('term_id','term_name')->orderBy('term_id')->get();
        $turnos    = DB::table('shifts')->select('shift_id','shift_name')->orderBy('shift_id')->get();

        // Distintos edificios disponibles según classrooms
        $edificios = DB::table('classrooms')
            ->select('building')
            ->distinct()
            ->orderBy('building')
            ->pluck('building');

        // Si el grupo ya tiene salón asignado, precargar building/floor/salón
        $selBuilding = null; $selFloor = null; $selClassroom = null;
        if (!empty($grupo->classroom_assigned)) {
            $c = DB::table('classrooms')
                ->where('classroom_id', (int)$grupo->classroom_assigned)
                ->first(['classroom_id','classroom_name','building','floor']);
            if ($c) {
                $selBuilding  = $c->building;
                $selFloor     = $c->floor;
                $selClassroom = $c;
            }
        }

        return view('grupos.edit', compact(
            'grupo','programas','terminos','turnos',
            'edificios','selBuilding','selFloor','selClassroom'
        ));
    }

    /* =================== UPDATE =================== */
    public function update(Request $request, $id)
    {
        $grupo = DB::table('groups')->where('group_id', (int)$id)->first();
        if (!$grupo) {
            return redirect()->route('grupos.index')->with('error', 'El grupo no existe o ya fue eliminado.');
        }

        $request->validate([
            'group_name'          => 'required|string|max:100',
            'program_id'          => 'required|integer|exists:programs,program_id',
            'term_id'             => 'required|integer|exists:terms,term_id',
            'turn_id'             => 'required|integer|exists:shifts,shift_id',
            'volume'              => 'nullable|integer|min:0',
            'classroom_assigned'  => 'nullable|integer|exists:classrooms,classroom_id',
            'lab_assigned'        => 'nullable|integer',
        ]);

        try {
            // Mantener área sincronizada con el programa
            $program = DB::table('programs')->where('program_id', $request->program_id)->first(['area']);

            // ===== Chequeo de EMPALMES si se especificó salón =====
            if ($request->filled('classroom_assigned')) {
                $classroomId = (int)$request->classroom_assigned;

                // s1: horario del grupo actual (ignoramos sesiones de laboratorio)
                // s2: cualquier asignación ACTIVA que ya ocupe ese salón y se traslape en día/hora
                $conflictos = DB::table('schedule_assignments as s1')
                    ->join('schedule_assignments as s2', function($j) use ($classroomId){
                        $j->on('s2.schedule_day', '=', 's1.schedule_day')
                          ->where('s2.classroom_id', '=', $classroomId)
                          ->whereIn('s2.estado', ['1','ACTIVO','activo'])
                          ->whereRaw('s2.start_time < s1.end_time')
                          ->whereRaw('s2.end_time   > s1.start_time');
                    })
                    ->leftJoin('groups as g2', 'g2.group_id', '=', 's2.group_id')
                    ->where('s1.group_id', (int)$id)
                    ->whereIn('s1.estado', ['1','ACTIVO','activo'])
                    ->whereNull('s1.lab_id') // solo sesiones de aula del grupo
                    ->whereColumn('s2.group_id', '!=', 's1.group_id')
                    ->select([
                        's2.group_id',
                        'g2.group_name',
                        's2.schedule_day',
                        's2.start_time',
                        's2.end_time',
                    ])
                    ->distinct()
                    ->get();

                if ($conflictos->isNotEmpty()) {
                    // armamos un mensaje amigable con hasta 6 conflictos
                    $lista = $conflictos->take(6)->map(function($r){
                        return "{$r->schedule_day} {$r->start_time}-{$r->end_time} (".$r->group_name.")";
                    })->implode('<br>');

                    return back()->withInput()->with('error',
                        "No se puede asignar el salón seleccionado por empalmes existentes:<br>".$lista.
                        ($conflictos->count() > 6 ? '<br>…' : '')
                    );
                }
            }

            // ===== Sin empalmes: actualizamos el grupo =====
            DB::table('groups')->where('group_id', (int)$id)->update([
                'group_name'         => $request->group_name,
                'program_id'         => $request->program_id,
                'area'               => $program->area ?? null,
                'term_id'            => $request->term_id,
                'volume'             => $request->volume ?? 0,
                'turn_id'            => $request->turn_id,
                'classroom_assigned' => $request->classroom_assigned ?: null,
                'lab_assigned'       => $request->lab_assigned ?: null,
                'fyh_actualizacion'  => now(),
            ]);

            if (class_exists(ActividadGeneral::class)) {
                ActividadGeneral::registrar('ACTUALIZAR', 'groups', (int)$id, "Actualizó el grupo {$request->group_name}");
            }

            return redirect()->route('grupos.index')->with('success', 'Grupo actualizado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al actualizar grupo', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Error de base de datos al actualizar el grupo.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al actualizar grupo', ['msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Ocurrió un error inesperado al actualizar el grupo.');
        }
    }

    /* =================== DESTROY =================== */
    public function destroy($id)
    {
        $g = DB::table('groups')->where('group_id', (int)$id)->first();
        if (!$g) {
            return redirect()->route('grupos.index')->with('error', 'El grupo no existe o ya fue eliminado.');
        }

        if (!Auth::user()->can('eliminar grupos')) {
            return redirect()->route('grupos.index')->with('error', 'No tienes permiso para eliminar grupos.');
        }

        $cntGS = DB::table('group_subjects')->where('group_id', (int)$id)->count();
        $cntTS = DB::table('teacher_subjects')->where('group_id', (int)$id)->count();
        $cntSA = DB::table('schedule_assignments')->where('group_id', (int)$id)->count();

        if ($cntGS || $cntTS || $cntSA) {
            return redirect()->route('grupos.index')->with(
                'error',
                'No se puede eliminar: el grupo tiene materias o asignaciones registradas.'
            );
        }

        try {
            DB::table('groups')->where('group_id', (int)$id)->delete();

            if (class_exists(ActividadGeneral::class)) {
                ActividadGeneral::registrar('ELIMINAR', 'groups', (int)$id, "Eliminó el grupo {$g->group_name}");
            }

            return redirect()->route('grupos.index')->with('success', 'Grupo eliminado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al eliminar grupo', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return redirect()->route('grupos.index')->with('error','Error de base de datos al eliminar el grupo.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al eliminar grupo', ['msg'=>$e->getMessage()]);
            return redirect()->route('grupos.index')->with('error','Ocurrió un error inesperado al eliminar el grupo.');
        }
    }
}
