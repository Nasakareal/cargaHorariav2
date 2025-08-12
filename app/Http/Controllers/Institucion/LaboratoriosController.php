<?php

namespace App\Http\Controllers\Institucion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use App\Models\ActividadGeneral;

class LaboratoriosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /* =================== INDEX =================== */
    public function index()
    {
        $laboratorios = DB::table('labs as l')
            ->select([
                'l.lab_id',
                'l.lab_name',
                'l.description',
                'l.area',
                'l.fyh_creacion',
                'l.fyh_actualizacion',
                // # de grupos distintos que usan el lab
                DB::raw('(SELECT COUNT(DISTINCT sa.group_id)
                          FROM schedule_assignments sa
                          WHERE sa.lab_id = l.lab_id) AS grupos_count'),
            ])
            ->orderBy('l.lab_name')
            ->get();

        return view('institucion.laboratorios.index', compact('laboratorios'));
    }

    /* =================== CREATE =================== */
    public function create()
    {
        // Áreas disponibles desde programs (fallback: areas ya usadas en labs)
        $areas = DB::table('programs')->whereNotNull('area')->distinct()->orderBy('area')->pluck('area');
        if ($areas->isEmpty()) {
            $areas = DB::table('labs')->whereNotNull('area')->distinct()->pluck('area');
            // Si vienen en CSV en labs.area, las explota:
            if ($areas->isNotEmpty()) {
                $areas = collect(
                    $areas->flatMap(function($csv){
                        return array_map('trim', explode(',', (string)$csv));
                    })->filter()->unique()->sort()->values()
                );
            }
        }

        return view('institucion.laboratorios.create', [
            'areas' => $areas, // collection de strings
        ]);
    }

    /* =================== STORE =================== */
    public function store(Request $request)
    {
        // Normaliza campos
        $request->merge([
            'lab_name'    => trim((string)$request->lab_name),
            'description' => $request->filled('description') ? trim((string)$request->description) : null,
        ]);

        // Set de áreas válidas
        $areasValidas = DB::table('programs')->whereNotNull('area')->distinct()->pluck('area');
        if ($areasValidas->isEmpty()) {
            $areasValidas = DB::table('labs')->whereNotNull('area')->distinct()->pluck('area');
            if ($areasValidas->isNotEmpty()) {
                $areasValidas = collect(
                    $areasValidas->flatMap(fn($csv) => array_map('trim', explode(',', (string)$csv)))
                )->filter()->unique()->values();
            }
        }

        $request->validate([
            'lab_name'    => ['required','string','max:150', Rule::unique('labs','lab_name')],
            'description' => ['nullable','string'],
            'areas'       => ['required','array','min:1'],
            'areas.*'     => $areasValidas->isNotEmpty()
                               ? ['string', Rule::in($areasValidas->toArray())]
                               : ['string'],
        ], [], [
            'lab_name' => 'nombre del laboratorio',
            'areas'    => 'áreas',
        ]);

        try {
            $areasCsv = implode(',', array_map('trim', (array)$request->areas));

            $id = DB::table('labs')->insertGetId([
                'lab_name'         => $request->lab_name,
                'description'      => $request->description,
                'area'             => $areasCsv, // CSV
                'fyh_creacion'     => now(),
                'fyh_actualizacion'=> null,
            ]);

            if (class_exists(ActividadGeneral::class)) {
                ActividadGeneral::registrar('CREAR', 'labs', $id, "Creó el laboratorio {$request->lab_name}");
            }

            return redirect()->route('institucion.laboratorios.index')->with('success','Laboratorio creado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al crear laboratorio', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Error de base de datos al crear el laboratorio.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al crear laboratorio', ['msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Ocurrió un error inesperado al crear el laboratorio.');
        }
    }

    /* =================== SHOW =================== */
    public function show($id)
    {
        $lab = DB::table('labs')->where('lab_id', (int)$id)->first([
            'lab_id','lab_name','description','area','fyh_creacion','fyh_actualizacion'
        ]);

        if (!$lab) {
            return redirect()->route('institucion.laboratorios.index')->with('error','El laboratorio no existe o ya fue eliminado.');
        }

        // Grupos que usan el laboratorio
        $grupos = DB::table('schedule_assignments as sa')
            ->join('groups as g', 'g.group_id', '=', 'sa.group_id')
            ->leftJoin('programs as p', 'p.program_id', '=', 'g.program_id')
            ->leftJoin('terms as tm', 'tm.term_id', '=', 'g.term_id')
            ->leftJoin('shifts as sh', 'sh.shift_id', '=', 'g.turn_id')
            ->where('sa.lab_id', (int)$id)
            ->select([
                'g.group_id',
                'g.group_name',
                DB::raw('COALESCE(p.program_name,"—") as program_name'),
                DB::raw('COALESCE(tm.term_name,"—") as term_name'),
                DB::raw('COALESCE(sh.shift_name,"—") as shift_name'),
            ])
            ->groupBy('g.group_id','g.group_name','p.program_name','tm.term_name','sh.shift_name')
            ->orderBy('program_name')
            ->orderByRaw('CAST(term_name AS UNSIGNED), term_name')
            ->orderBy('g.group_name')
            ->get();

        return view('institucion.laboratorios.show', [
            'lab'    => $lab,
            'grupos' => $grupos,
        ]);
    }

    /* =================== EDIT =================== */
    public function edit($id)
    {
        $lab = DB::table('labs')->where('lab_id', (int)$id)->first();

        if (!$lab) {
            return redirect()->route('institucion.laboratorios.index')->with('error','El laboratorio no existe.');
        }

        // Áreas disponibles
        $areas = DB::table('programs')->whereNotNull('area')->distinct()->orderBy('area')->pluck('area');
        if ($areas->isEmpty()) {
            $areas = DB::table('labs')->whereNotNull('area')->distinct()->pluck('area');
            if ($areas->isNotEmpty()) {
                $areas = collect(
                    $areas->flatMap(fn($csv) => array_map('trim', explode(',', (string)$csv)))
                )->filter()->unique()->sort()->values();
            }
        }

        $areasSeleccionadas = collect(
            array_map('trim', explode(',', (string)$lab->area))
        )->filter()->values()->all();

        return view('institucion.laboratorios.edit', [
            'lab'                => $lab,
            'areas'              => $areas,
            'areasSeleccionadas' => $areasSeleccionadas,
        ]);
    }

    /* =================== UPDATE =================== */
    public function update(Request $request, $id)
    {
        $lab = DB::table('labs')->where('lab_id', (int)$id)->first();
        if (!$lab) {
            return redirect()->route('institucion.laboratorios.index')->with('error','El laboratorio no existe o ya fue eliminado.');
        }

        $request->merge([
            'lab_name'    => trim((string)$request->lab_name),
            'description' => $request->filled('description') ? trim((string)$request->description) : null,
        ]);

        // Set de áreas válidas
        $areasValidas = DB::table('programs')->whereNotNull('area')->distinct()->pluck('area');
        if ($areasValidas->isEmpty()) {
            $areasValidas = DB::table('labs')->whereNotNull('area')->distinct()->pluck('area');
            if ($areasValidas->isNotEmpty()) {
                $areasValidas = collect(
                    $areasValidas->flatMap(fn($csv) => array_map('trim', explode(',', (string)$csv)))
                )->filter()->unique()->values();
            }
        }

        $request->validate([
            'lab_name'    => ['required','string','max:150', Rule::unique('labs','lab_name')->ignore((int)$id, 'lab_id')],
            'description' => ['nullable','string'],
            'areas'       => ['required','array','min:1'],
            'areas.*'     => $areasValidas->isNotEmpty()
                               ? ['string', Rule::in($areasValidas->toArray())]
                               : ['string'],
        ], [], [
            'lab_name' => 'nombre del laboratorio',
            'areas'    => 'áreas',
        ]);

        try {
            $areasCsv = implode(',', array_map('trim', (array)$request->areas));

            DB::table('labs')->where('lab_id', (int)$id)->update([
                'lab_name'         => $request->lab_name,
                'description'      => $request->description,
                'area'             => $areasCsv,
                'fyh_actualizacion'=> now(),
            ]);

            if (class_exists(ActividadGeneral::class)) {
                ActividadGeneral::registrar('ACTUALIZAR', 'labs', (int)$id, "Actualizó el laboratorio {$request->lab_name}");
            }

            return redirect()->route('institucion.laboratorios.index')->with('success','Laboratorio actualizado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al actualizar laboratorio', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Error de base de datos al actualizar el laboratorio.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al actualizar laboratorio', ['msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Ocurrió un error inesperado al actualizar el laboratorio.');
        }
    }

    /* =================== DESTROY =================== */
    public function destroy($id)
    {
        $lab = DB::table('labs')->where('lab_id', (int)$id)->first();
        if (!$lab) {
            return redirect()->route('institucion.laboratorios.index')->with('error','El laboratorio no existe o ya fue eliminado.');
        }

        if (!Auth::user()->can('eliminar laboratorios')) {
            return redirect()->route('institucion.laboratorios.index')->with('error','No tienes permiso para eliminar laboratorios.');
        }

        // Bloquear borrado si está usado en horarios
        $cntSA = DB::table('schedule_assignments')->where('lab_id', (int)$id)->count();
        if ($cntSA) {
            return redirect()->route('institucion.laboratorios.index')->with(
                'error',
                'No se puede eliminar: el laboratorio tiene asignaciones de horario registradas.'
            );
        }

        try {
            DB::table('labs')->where('lab_id', (int)$id)->delete();

            if (class_exists(ActividadGeneral::class)) {
                ActividadGeneral::registrar('ELIMINAR', 'labs', (int)$id, "Eliminó el laboratorio {$lab->lab_name}");
            }

            return redirect()->route('institucion.laboratorios.index')->with('success','Laboratorio eliminado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al eliminar laboratorio', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return redirect()->route('institucion.laboratorios.index')->with('error','Error de base de datos al eliminar el laboratorio.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al eliminar laboratorio', ['msg'=>$e->getMessage()]);
            return redirect()->route('institucion.laboratorios.index')->with('error','Ocurrió un error inesperado al eliminar el laboratorio.');
        }
    }

    /* =================== HORARIO =================== */
    public function horario(int $id)
    {
        $lab = DB::table('labs')->where('lab_id', (int)$id)->first([
            'lab_id','lab_name','description','area','fyh_creacion','fyh_actualizacion'
        ]);

        if (!$lab) {
            return redirect()->route('institucion.laboratorios.index')->with('error','El laboratorio no existe.');
        }

        // Expresiones seguras para materia/docente según columnas existentes
        $schema = DB::getSchemaBuilder();

        $subCandidates = ['subject_name','nombre','name','titulo'];
        $subCols = array_values(array_filter($subCandidates, fn($c) => $schema->hasColumn('subjects', $c)));
        $materiaExpr = $subCols
            ? 'COALESCE('.implode(', ', array_map(fn($c) => "m.$c", $subCols)).')'
            : "'Materia'";

        $teaCandidates = ['teacher_name','nombre_completo','full_name','name','nombre'];
        $teaCols = array_values(array_filter($teaCandidates, fn($c) => $schema->hasColumn('teachers', $c)));
        $docenteExpr = $teaCols
            ? 'COALESCE('.implode(', ', array_map(fn($c) => "t.$c", $teaCols)).')'
            : "'Sin profesor'";

        $rows = DB::table('schedule_assignments as s')
            ->leftJoin('subjects as m', 'm.subject_id', '=', 's.subject_id')
            ->leftJoin('groups   as g', 'g.group_id',   '=', 's.group_id')
            ->leftJoin('teachers as t', 't.teacher_id', '=', 's.teacher_id')
            ->where('s.lab_id', (int)$id)
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

        // Días presentes (orden L->D)
        $orderDia = ['Lunes'=>1,'Martes'=>2,'Miércoles'=>3,'Miercoles'=>3,'Jueves'=>4,'Viernes'=>5,'Sábado'=>6,'Sabado'=>6,'Domingo'=>7];
        $dias = $rows->isEmpty()
            ? ['Lunes','Martes','Miércoles','Jueves','Viernes']
            : $rows->pluck('schedule_day')->map(fn($d) => $this->canonDia($d))
                   ->unique()->sortBy(fn($d) => $orderDia[$d] ?? 99)->values()->all();

        // Rango horario
        [$min, $max] = $this->minMaxHoras($rows);
        $min = $min ?: '07:00:00';
        $max = $max ?: '20:00:00';
        $slots = $this->slots($min, $max, 60);

        // Matriz slot x día
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

        return view('institucion.laboratorios.horario', [
            'lab'   => $lab,
            'dias'  => $dias,
            'horas' => $slots,
            'tabla' => $tabla,
        ]);
    }

    /* =================== Helpers =================== */
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
