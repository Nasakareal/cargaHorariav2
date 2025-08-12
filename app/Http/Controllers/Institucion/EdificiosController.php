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

class EdificiosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /* =================== INDEX =================== */
    public function index()
    {
        $edificios = DB::table('building_programs as bp')
            ->selectRaw("
                MIN(bp.id)                                      as row_id,
                bp.building_name,
                MAX(COALESCE(bp.planta_baja,0))                 as planta_baja,
                MAX(COALESCE(bp.planta_alta,0))                 as planta_alta,
                GROUP_CONCAT(DISTINCT bp.area ORDER BY bp.area SEPARATOR ', ') as areas,
                NULLIF(SUBSTRING_INDEX(bp.building_name,'-',-1),'') as building_token,
                (SELECT COUNT(*) FROM classrooms c WHERE c.building = bp.building_name) as classrooms_count
            ")
            ->groupBy('bp.building_name')
            ->orderBy('building_token')
            ->orderBy('bp.building_name')
            ->get();

        return view('institucion.edificios.index', compact('edificios'));
    }

    /* ============ CREATE ============ */
    public function create()
    {
        $areas = DB::table('programs')->whereNotNull('area')
            ->distinct()->orderBy('area')->pluck('area');

        if ($areas->isEmpty()) {
            $areas = DB::table('building_programs')->whereNotNull('area')
                ->distinct()->orderBy('area')->pluck('area');
        }

        return view('institucion.edificios.create', compact('areas'));
    }

    /* ============ STORE ============ */
    public function store(Request $request)
    {
        $request->merge([
            'building_name' => strtoupper(trim((string)$request->building_name)),
        ]);

        $areasValidas = DB::table('programs')->whereNotNull('area')
            ->distinct()->pluck('area');
        if ($areasValidas->isEmpty()) {
            $areasValidas = DB::table('building_programs')->whereNotNull('area')
                ->distinct()->pluck('area');
        }

        $request->validate([
            'building_name' => [
                'required','string','max:100',
                Rule::unique('building_programs','building_name'),
            ],
            'planta_alta' => ['required','in:0,1'],
            'planta_baja' => ['required','in:0,1'],
            'areas'       => ['required','array','min:1'],
            'areas.*'     => ['string', Rule::in($areasValidas->toArray())],
        ], [], [
            'building_name' => 'nombre del edificio',
            'planta_alta'   => 'planta alta',
            'planta_baja'   => 'planta baja',
            'areas'         => 'áreas',
        ]);

        try {
            DB::transaction(function() use ($request){
                foreach ($request->areas as $area) {
                    DB::table('building_programs')->insert([
                        'building_name'    => $request->building_name,
                        'area'             => $area,
                        'planta_alta'      => (int)$request->planta_alta,
                        'planta_baja'      => (int)$request->planta_baja,
                        'fyh_creacion'     => now(),
                        'fyh_actualizacion'=> null,
                    ]);
                }
            });

            return redirect()->route('institucion.edificios.index')
                ->with('success','Edificio creado correctamente.');
        } catch (QueryException $e) {
            Log::error('BD edificios.store', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Error de base de datos al crear el edificio.');
        } catch (\Throwable $e) {
            Log::error('Catch edificios.store', ['msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Ocurrió un error inesperado al crear el edificio.');
        }
    }

    /* =================== SHOW =================== */
    public function show($id)
    {
        $row = DB::table('building_programs')->where('id', (int)$id)->first();
        if (!$row) {
            return redirect()->route('institucion.edificios.index')->with('error','El edificio no existe.');
        }

        $building = $row->building_name;
        $edificio = DB::table('building_programs')
            ->where('building_name', $building)
            ->selectRaw("
                MIN(id) as row_id,
                building_name,
                NULLIF(SUBSTRING_INDEX(building_name, '-', -1), '') as building_token,
                MAX(COALESCE(planta_baja,0)) as planta_baja,
                MAX(COALESCE(planta_alta,0)) as planta_alta,
                GROUP_CONCAT(DISTINCT area ORDER BY area SEPARATOR ', ') as areas,
                MIN(fyh_creacion) as fyh_creacion_min,
                MAX(fyh_actualizacion) as fyh_actualizacion_max
            ")
            ->groupBy('building_name')
            ->first();

        $salones = DB::table('classrooms as c')
            ->where('c.building', $building)
            ->select([
                'c.classroom_id','c.classroom_name','c.floor','c.capacity','c.estado',
                DB::raw("CASE UPPER(c.floor) WHEN 'BAJA' THEN 0 WHEN 'ALTA' THEN 1 ELSE 2 END as floor_order"),
                DB::raw('(SELECT COUNT(DISTINCT sa.group_id) FROM schedule_assignments sa WHERE sa.classroom_id = c.classroom_id) as grupos_count'),
            ])
            ->orderBy('floor_order')
            ->orderByRaw('CAST(c.classroom_name AS UNSIGNED), c.classroom_name')
            ->get();

        $salonesPorPlanta = DB::table('classrooms')
            ->where('building', $building)
            ->selectRaw("COALESCE(UPPER(floor), '—') as piso, COUNT(*) as cnt")
            ->groupBy('piso')
            ->pluck('cnt','piso')
            ->toArray();

        return view('institucion.edificios.show', [
            'rowId'             => (int)($edificio->row_id ?? $id),
            'edificio'          => $edificio,
            'salones'           => $salones,
            'salonesPorPlanta'  => $salonesPorPlanta,
        ]);
    }


    /* =================== EDIT =================== */
    public function edit($id)
    {
        $row = DB::table('building_programs')->where('id', (int)$id)->first();
        if (!$row) {
            return redirect()->route('institucion.edificios.index')
                ->with('error','El edificio no existe.');
        }

        $building = $row->building_name;

        $agregado = DB::table('building_programs')
            ->where('building_name', $building)
            ->selectRaw("
                building_name,
                MAX(COALESCE(planta_baja,0)) as planta_baja,
                MAX(COALESCE(planta_alta,0)) as planta_alta
            ")
            ->groupBy('building_name')
            ->first();

        $planta_baja = (int)($agregado->planta_baja ?? 0);
        $planta_alta = (int)($agregado->planta_alta ?? 0);

        $areasSeleccionadas = DB::table('building_programs')
            ->where('building_name', $building)
            ->pluck('area')
            ->toArray();

        $areas = DB::table('programs')->whereNotNull('area')
            ->distinct()->orderBy('area')->pluck('area');
        if ($areas->isEmpty()) {
            $areas = DB::table('building_programs')->whereNotNull('area')
                ->distinct()->orderBy('area')->pluck('area');
        }

        return view('institucion.edificios.edit', [
            'rowId'              => (int)$id,
            'building'           => $building,
            'planta_baja'        => $planta_baja,
            'planta_alta'        => $planta_alta,
            'areas'              => $areas,
            'areasSeleccionadas' => $areasSeleccionadas,
        ]);
    }

    /* =================== UPDATE =================== */
    public function update(Request $request, $id)
    {
        $row = DB::table('building_programs')->where('id', (int)$id)->first();
        if (!$row) {
            return redirect()->route('institucion.edificios.index')
                ->with('error','El edificio no existe.');
        }

        $oldName = $row->building_name;

        $request->merge([
            'building_name' => strtoupper(trim((string)$request->building_name)),
            'planta_baja'   => (string)$request->planta_baja === '1' ? 1 : 0,
            'planta_alta'   => (string)$request->planta_alta === '1' ? 1 : 0,
        ]);

        $areasValidas = DB::table('programs')->whereNotNull('area')
            ->distinct()->pluck('area');
        if ($areasValidas->isEmpty()) {
            $areasValidas = DB::table('building_programs')->whereNotNull('area')
                ->distinct()->pluck('area');
        }

        $request->validate([
            'building_name' => [
                'required','string','max:100',
                Rule::unique('building_programs','building_name')->ignore($oldName, 'building_name'),
            ],
            'planta_baja' => ['required','in:0,1'],
            'planta_alta' => ['required','in:0,1'],
            'areas'       => ['required','array','min:1'],
            'areas.*'     => ['string', Rule::in($areasValidas->toArray())],
        ], [], [
            'building_name' => 'edificio',
            'planta_baja'   => 'planta baja',
            'planta_alta'   => 'planta alta',
            'areas'         => 'áreas',
        ]);

        try {
            DB::transaction(function () use ($request, $oldName) {
                // 1) Borra todas las filas del edificio actual
                DB::table('building_programs')->where('building_name', $oldName)->delete();

                // 2) Inserta las filas nuevas (una por área)
                foreach ($request->areas as $area) {
                    DB::table('building_programs')->insert([
                        'building_name'     => $request->building_name,
                        'area'              => $area,
                        'planta_baja'       => $request->planta_baja,
                        'planta_alta'       => $request->planta_alta,
                        'fyh_creacion'      => now(),
                        'fyh_actualizacion' => null,
                    ]);
                }

                // 3) Si renombraron el edificio, sincroniza classrooms.building
                if ($oldName !== $request->building_name) {
                    DB::table('classrooms')
                        ->where('building', $oldName)
                        ->update([
                            'building'          => $request->building_name,
                            'fyh_actualizacion' => now(),
                        ]);
                }
            });

            return redirect()->route('institucion.edificios.index')
                ->with('success','Edificio actualizado correctamente.');
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Error BD al actualizar edificio', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Error de base de datos al actualizar el edificio.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al actualizar edificio', ['msg'=>$e->getMessage()]);
            return back()->withInput()->with('error','Ocurrió un error inesperado al actualizar el edificio.');
        }
    }

    /* =================== DESTROY =================== */
    public function destroy($id)
    {
        $row = DB::table('building_programs')->where('id', (int)$id)->first();
        if (!$row) {
            return redirect()->route('edificios.index')->with('error','El edificio no existe.');
        }
        $building = $row->building_name;

        if (!Auth::user()->can('eliminar edificios')) {
            return redirect()->route('edificios.index')->with('error','No tienes permiso para eliminar edificios.');
        }

        $cntClass = DB::table('classrooms')->where('building', $building)->count();
        if ($cntClass > 0) {
            return redirect()->route('edificios.index')->with(
                'error',
                'No se puede eliminar: existen salones asociados a este edificio.'
            );
        }

        try {
            DB::table('building_programs')->where('building_name', $building)->delete();

            if (class_exists(ActividadGeneral::class)) {
                ActividadGeneral::registrar('ELIMINAR', 'building_programs', (int)$id, "Eliminó edificio {$building}");
            }

            return redirect()->route('edificios.index')->with('success','Edificio eliminado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al eliminar edificio', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return redirect()->route('edificios.index')->with('error','Error de base de datos al eliminar el edificio.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al eliminar edificio', ['msg'=>$e->getMessage()]);
            return redirect()->route('edificios.index')->with('error','Ocurrió un error inesperado al eliminar el edificio.');
        }
    }
}
