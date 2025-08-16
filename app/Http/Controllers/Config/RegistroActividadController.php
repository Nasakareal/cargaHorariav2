<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\ActividadGeneral;
use Illuminate\Http\Request;

class RegistroActividadController extends Controller
{
    public function index(Request $request)
    {
        $q = ActividadGeneral::query();

        // Filtros opcionales
        if ($request->filled('accion'))  $q->where('accion', strtoupper($request->accion));
        if ($request->filled('tabla'))   $q->where('tabla', $request->tabla);
        if ($request->filled('usuario')) $q->where('nombre_usuario', 'like', '%'.$request->usuario.'%');
        if ($request->filled('desde'))   $q->whereDate('fecha', '>=', $request->desde);
        if ($request->filled('hasta'))   $q->whereDate('fecha', '<=', $request->hasta);

        $actividades = $q->orderBy('fecha')->paginate(20)->withQueryString();

        return view('configuracion.registro_actividad.index', compact('actividades'));
    }

    public function show($id)
    {
        $actividad = ActividadGeneral::findOrFail($id);
        return view('configuracion.registro_actividad.show', compact('actividad'));
    }
}
