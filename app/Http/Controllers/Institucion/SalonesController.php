<?php

namespace App\Http\Controllers\Institucion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Models\ActividadGeneral;
use Illuminate\Validation\Rule;

class SalonesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /* =================== INDEX =================== */
    public function index()
    {
        $salones = DB::table('classrooms as c')
            ->select([
                'c.classroom_id',
                'c.classroom_name',
                'c.capacity',
                'c.building',
                'c.floor',
                'c.estado',
                'c.fyh_creacion',
                'c.fyh_actualizacion',
                // Grupos distintos que usan el salón
                DB::raw('(SELECT COUNT(DISTINCT sa.group_id) 
                          FROM schedule_assignments sa 
                          WHERE sa.classroom_id = c.classroom_id) AS grupos_count'),
                // Token después del guion: EDIFICIO-A -> A ; EDIFICIO-P1 -> P1 (si no hay guion, usa el texto completo)
                DB::raw('NULLIF(SUBSTRING_INDEX(c.building, "-", -1), "") AS building_token'),
                DB::raw('CONCAT(c.classroom_name, "(", NULLIF(SUBSTRING_INDEX(c.building, "-", -1), ""), ")") AS nombre_formateado'),
                // Para ordenar planta BAJA antes que ALTA
                DB::raw("CASE UPPER(c.floor) WHEN 'BAJA' THEN 0 WHEN 'ALTA' THEN 1 ELSE 2 END AS floor_order"),
            ])
            ->orderBy('building_token')
            ->orderBy('floor_order')
            ->orderByRaw('CAST(c.classroom_name AS UNSIGNED), c.classroom_name')
            ->get();

        return view('institucion.salones.index', compact('salones'));
    }

    /* =================== CREATE =================== */
    public function create()
    {
        // Edificios desde building_programs; si no hay, toma distinct de classrooms
        $edificios = DB::table('building_programs')
            ->select('building_name')
            ->distinct()
            ->orderBy('building_name')
            ->pluck('building_name');

        if ($edificios->isEmpty()) {
            $edificios = DB::table('classrooms')
                ->select('building')
                ->distinct()
                ->orderBy('building')
                ->pluck('building');
        }

        return view('institucion.salones.create', [
            'edificios' => $edificios,
        ]);
    }

    /* =================== STORE =================== */
    public function store(Request $request)
    {
        $request->merge([
            // por si llega en minúsculas desde el form
            'building' => strtoupper(trim((string)$request->building)),
            'floor'    => strtoupper(trim((string)$request->floor)),
        ]);

        $request->validate([
            'classroom_name' => 'required|string|max:50',
            'building'       => 'required|string|max:100',
            'floor'          => 'nullable|in:ALTA,BAJA',
            'capacity'       => 'nullable|integer|min:0',
        ], [], [
            'classroom_name' => 'salón',
            'building'       => 'edificio',
            'floor'          => 'planta',
            'capacity'       => 'capacidad',
        ]);

        try {
            $id = DB::table('classrooms')->insertGetId([
                'classroom_name'   => $request->classroom_name,
                'building'         => $request->building,
                'floor'            => $request->floor ?: null,
                'capacity'         => $request->capacity ?? 0,
                'fyh_creacion'     => now(),
                'fyh_actualizacion'=> null,
                'estado'           => 'ACTIVO',
            ]);

            if (class_exists(ActividadGeneral::class)) {
                ActividadGeneral::registrar('CREAR', 'classrooms', $id, "Creó el salón {$request->classroom_name}");
            }

            return redirect()
                ->route('institucion.salones.index')
                ->with('success', 'Salón creado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al crear salón', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Error de base de datos al crear el salón.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al crear salón', ['msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Ocurrió un error inesperado al crear el salón.');
        }
    }

    /* =================== SHOW =================== */
    public function show($id)
    {
        $s = DB::table('classrooms as c')
            ->where('c.classroom_id', (int)$id)
            ->first([
                'c.classroom_id',
                'c.classroom_name',
                'c.capacity',
                'c.building',
                'c.floor',
                'c.estado',
                'c.fyh_creacion',
                'c.fyh_actualizacion',
            ]);

        if (!$s) {
            return redirect()->route('institucion.salones.index')
                ->with('error', 'El salón no existe o ya fue eliminado.');
        }

        // Lista de grupos que usan este salón
        $grupos = DB::table('schedule_assignments as sa')
            ->join('groups as g', 'g.group_id', '=', 'sa.group_id')
            ->leftJoin('programs as p', 'p.program_id', '=', 'g.program_id')
            ->leftJoin('terms as tm', 'tm.term_id', '=', 'g.term_id')
            ->leftJoin('shifts as sh', 'sh.shift_id', '=', 'g.turn_id')
            ->where('sa.classroom_id', (int)$id)
            ->select([
                'g.group_id',
                'g.group_name',
                DB::raw('COALESCE(p.program_name,"—") as program_name'),
                DB::raw('COALESCE(tm.term_name,"—") as term_name'),
                DB::raw('COALESCE(sh.shift_name,"—") as shift_name'),
            ])
            ->groupBy('g.group_id', 'g.group_name', 'p.program_name', 'tm.term_name', 'sh.shift_name')
            ->orderBy('program_name')
            ->orderByRaw('CAST(term_name AS UNSIGNED), term_name')
            ->orderBy('g.group_name')
            ->get();

        return view('institucion.salones.show', [
            'salon' => $s,
            'grupos' => $grupos
        ]);
    }

    /* =================== EDIT =================== */
    public function edit($id)
    {
        $salon = DB::table('classrooms')->where('classroom_id', (int)$id)->first();

        if (!$salon) {
            return redirect()->route('institucion.salones.index')->with('error', 'El salón no existe.');
        }

        $edificios = DB::table('building_programs')->select('building_name')->distinct()->orderBy('building_name')->pluck('building_name');

        if ($edificios->isEmpty()) {
            $edificios = DB::table('classrooms')->select('building')->distinct()->orderBy('building')->pluck('building');
        }

        $plantas = ['ALTA', 'BAJA'];

        return view('institucion.salones.edit', compact('salon','edificios','plantas'));
    }

    /* =================== UPDATE =================== */
    public function update(Request $request, $id)
    {
        $salon = DB::table('classrooms')
            ->where('classroom_id', (int)$id)
            ->first();

        if (!$salon) {
            return redirect()
                ->route('institucion.salones.index')
                ->with('error', 'El salón no existe o ya fue eliminado.');
        }

        $request->merge([
            'building' => strtoupper(trim((string)$request->building)),
            'floor'    => $request->filled('floor') ? strtoupper(trim((string)$request->floor)) : null,
        ]);

        $edificios = DB::table('building_programs')->distinct()->pluck('building_name');
        if ($edificios->isEmpty()) {
            $edificios = DB::table('classrooms')->distinct()->pluck('building');
        }

        $request->validate([
            'classroom_name' => 'required|string|max:50',
            'building'       => ['required','string','max:100', Rule::in($edificios->toArray())],
            'floor'          => ['nullable', Rule::in(['ALTA','BAJA'])],
            'capacity'       => 'nullable|integer|min:0',
        ], [], [
            'classroom_name' => 'salón',
            'building'       => 'edificio',
            'floor'          => 'planta',
            'capacity'       => 'capacidad',
        ]);

        try {
            DB::table('classrooms')
                ->where('classroom_id', (int)$id)
                ->update([
                    'classroom_name'   => $request->classroom_name,
                    'building'         => $request->building,
                    'floor'            => $request->floor ?: null,
                    'capacity'         => $request->capacity ?? 0,
                    'fyh_actualizacion'=> now(),
                ]);

            if (class_exists(ActividadGeneral::class)) {
                ActividadGeneral::registrar(
                    'ACTUALIZAR',
                    'classrooms',
                    (int)$id,
                    "Actualizó el salón {$request->classroom_name}"
                );
            }

            return redirect()
                ->route('institucion.salones.index')
                ->with('success', 'Salón actualizado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al actualizar salón', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Error de base de datos al actualizar el salón.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al actualizar salón', ['msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Ocurrió un error inesperado al actualizar el salón.');
        }
    }

    /* =================== DESTROY =================== */
    public function destroy($id)
    {
        $s = DB::table('classrooms')->where('classroom_id', (int)$id)->first();
        if (!$s) {
            return redirect()->route('institucion.salones.index')->with('error', 'El salón no existe o ya fue eliminado.');
        }

        if (!Auth::user()->can('eliminar salones')) {
            return redirect()->route('institucion.salones.index')->with('error', 'No tienes permiso para eliminar salones.');
        }

        // Bloquear borrado si está usado en horarios
        $cntSA = DB::table('schedule_assignments')->where('classroom_id', (int)$id)->count();
        if ($cntSA) {
            return redirect()->route('institucion.salones.index')->with(
                'error',
                'No se puede eliminar: el salón tiene asignaciones de horario registradas.'
            );
        }

        try {
            DB::table('classrooms')->where('classroom_id', (int)$id)->delete();

            if (class_exists(ActividadGeneral::class)) {
                ActividadGeneral::registrar('ELIMINAR', 'classrooms', (int)$id, "Eliminó el salón {$s->classroom_number}");
            }

            return redirect()->route('institucion.salones.index')->with('success', 'Salón eliminado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al eliminar salón', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return redirect()->route('institucion.salones.index')->with('error','Error de base de datos al eliminar el salón.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al eliminar salón', ['msg'=>$e->getMessage()]);
            return redirect()->route('institucion.salones.index')->with('error','Ocurrió un error inesperado al eliminar el salón.');
        }
    }

    public function horario(int $id)
    {
        // 1) Info del salón
        $salon = DB::table('classrooms as c')
            ->where('c.classroom_id', $id)
            ->first(['c.classroom_id','c.classroom_name','c.building','c.floor','c.capacity','c.estado','c.fyh_creacion','c.fyh_actualizacion']);

        if (!$salon) {
            return redirect()->route('institucion.salones.index')->with('error','El salón no existe.');
        }

        // 2) Construir expresiones SEGURO según columnas reales
        $schema = DB::getSchemaBuilder();

        $subCandidates = ['subject_name','nombre','name','titulo'];
        $subCols = array_values(array_filter($subCandidates, fn($c) => $schema->hasColumn('subjects', $c)));
        $materiaExpr = $subCols && count($subCols) > 0
            ? 'COALESCE('.implode(', ', array_map(fn($c) => "m.$c", $subCols)).')'
            : "'Materia'";

        $teaCandidates = ['teacher_name','nombre_completo','full_name','name','nombre'];
        $teaCols = array_values(array_filter($teaCandidates, fn($c) => $schema->hasColumn('teachers', $c)));
        $docenteExpr = $teaCols && count($teaCols) > 0
            ? 'COALESCE('.implode(', ', array_map(fn($c) => "t.$c", $teaCols)).')'
            : "'Sin profesor'";

        // 3) Asignaciones del salón (SOLO columnas que sí existen)
        $rows = DB::table('schedule_assignments as s')
            ->leftJoin('subjects as m', 'm.subject_id', '=', 's.subject_id')
            ->leftJoin('groups   as g', 'g.group_id',   '=', 's.group_id')
            ->leftJoin('teachers as t', 't.teacher_id', '=', 's.teacher_id')
            ->where('s.classroom_id', $id)
            ->when($schema->hasColumn('schedule_assignments','estado'), function($q){
                $q->whereIn('s.estado', ['1','ACTIVO','activo']);
            })
            ->select([
                's.schedule_day',
                's.start_time',
                's.end_time',
                DB::raw("$materiaExpr as materia"),
                'g.group_name',
                DB::raw("$docenteExpr as docente"),
            ])
            ->orderBy('s.start_time')
            ->get();

        // 4) Días presentes (orden L->D)
        $orderDia = ['Lunes'=>1,'Martes'=>2,'Miércoles'=>3,'Miercoles'=>3,'Jueves'=>4,'Viernes'=>5,'Sábado'=>6,'Sabado'=>6,'Domingo'=>7];
        $dias = $rows->isEmpty()
            ? ['Lunes','Martes','Miércoles','Jueves','Viernes']
            : $rows->pluck('schedule_day')
                   ->map(fn($d) => $this->canonDia($d))
                   ->unique()
                   ->sortBy(fn($d) => $orderDia[$d] ?? 99)
                   ->values()->all();

        // 5) Rango horario a partir de los datos
        [$min, $max] = $this->minMaxHoras($rows);
        $min = $min ?: '07:00:00';
        $max = $max ?: '20:00:00';
        $slots = $this->slots($min, $max, 60);

        // 6) Matriz [slot][día] => HTML
        $tabla = [];
        foreach ($slots as $h) foreach ($dias as $d) { $tabla[$h][$d] = ''; }

        foreach ($rows as $r) {
            $d = $this->canonDia($r->schedule_day);
            if (!in_array($d, $dias, true)) continue;

            foreach ($slots as $slot) {
                [$hs, $he] = explode(' - ', $slot); $hs .= ':00'; $he .= ':00';
                if (!$this->overlap($hs, $he, $r->start_time, $r->end_time)) continue;

                $linea = e($r->materia).' — '.e($r->group_name ?? '—').' — '.e($r->docente);
                $tabla[$slot][$d] = $tabla[$slot][$d] ? ($tabla[$slot][$d].'<br>'.$linea) : $linea;
            }
        }

        return view('institucion.salones.horario', [
            'salon' => $salon,
            'dias'  => $dias,
            'horas' => $slots,
            'tabla' => $tabla,
        ]);
    }

    /* =================== Helpers (colócalos en el controller) =================== */

    protected function canonDia($d): string
    {
        $k = mb_strtolower(trim((string)$d), 'UTF-8');
        return match ($k) {
            'lunes' => 'Lunes',
            'martes' => 'Martes',
            'miercoles','miércoles' => 'Miércoles',
            'jueves' => 'Jueves',
            'viernes' => 'Viernes',
            'sabado','sábado' => 'Sábado',
            'domingo' => 'Domingo',
            default => 'Lunes',
        };
    }

    protected function minMaxHoras($rows): array
    {
        if (!$rows || $rows->isEmpty()) return [null,null];
        $mins = $rows->pluck('start_time')->filter()->all();
        $maxs = $rows->pluck('end_time')->filter()->all();
        return [ $mins ? min($mins) : null, $maxs ? max($maxs) : null ];
    }

    protected function slots(string $inicio, string $fin, int $step = 60): array
    {
        $out = [];
        $t = \Carbon\Carbon::createFromFormat('H:i:s', $inicio);
        $E = \Carbon\Carbon::createFromFormat('H:i:s', $fin);
        while ($t < $E) {
            $n = $t->copy()->addMinutes($step);
            $out[] = $t->format('H:i').' - '.$n->format('H:i');
            $t = $n;
        }
        return $out;
    }

    protected function overlap(string $aS, string $aE, ?string $bS, ?string $bE): bool
    {
        if (!$bS || !$bE) return false;
        return ($aS < $bE) && ($aE > $bS);
    }
}
