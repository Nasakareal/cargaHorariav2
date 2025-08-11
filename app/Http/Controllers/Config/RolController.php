<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\ActividadGeneral;

class RolController extends Controller
{
    public function index()
    {
        // Lista de roles con # de usuarios y permisos
        $roles = Role::withCount('users')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get();

        return view('configuracion.roles.index', compact('roles'));
    }

    public function create()
    {
        $permisos = Permission::orderBy('name')->get();
        return view('configuracion.roles.create', compact('permisos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255|unique:roles,name',
            'permisos'  => 'nullable|array',
            'permisos.*'=> 'integer|exists:permissions,id',
        ], [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.unique'   => 'Ese rol ya existe.',
        ], [
            'name' => 'nombre del rol',
        ]);

        try {
            $rol = Role::create([
                'name'       => $request->name,
                'guard_name' => 'web',
            ]);

            if ($request->filled('permisos')) {
                $rol->syncPermissions($request->permisos);
            }

            ActividadGeneral::registrar('CREAR', 'roles', $rol->id, "Creó el rol {$rol->name}");

            return redirect()
                ->route('configuracion.roles.index')
                ->with('success', 'Rol creado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al crear rol', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return redirect()
                ->route('configuracion.roles.index')
                ->with('error', 'Error de base de datos al crear el rol.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al crear rol', ['msg'=>$e->getMessage()]);
            return redirect()
                ->route('configuracion.roles.index')
                ->with('error', 'Ocurrió un error inesperado al crear el rol.');
        }
    }

    public function show($id)
    {
        $rol = Role::with('permissions:id,name')->withCount('users')->findOrFail($id);
        return view('configuracion.roles.show', compact('rol'));
    }

    public function edit($id)
    {
        $rol = Role::with('permissions:id')->findOrFail($id);
        $permisos = Permission::orderBy('name')->get();
        $permisosSeleccionados = $rol->permissions->pluck('id')->all();

        return view('configuracion.roles.edit', compact('rol','permisos','permisosSeleccionados'));
    }

    public function update(Request $request, $id)
    {
        $rol = Role::find($id);
        if (!$rol) {
            return redirect()->route('configuracion.roles.index')
                ->with('error', 'El rol no existe o ya fue eliminado.');
        }

        $request->validate([
            'name'      => 'required|string|max:255|unique:roles,name,' . $rol->id,
            'permisos'  => 'nullable|array',
            'permisos.*'=> 'integer|exists:permissions,id',
        ], [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.unique'   => 'Ese rol ya existe.',
        ], [
            'name' => 'nombre del rol',
        ]);

        try {
            $rol->name = $request->name;
            $rol->save();

            // Sincroniza permisos (si no viene nada, limpia)
            $rol->syncPermissions($request->permisos ?? []);

            ActividadGeneral::registrar('ACTUALIZAR', 'roles', $rol->id, "Actualizó el rol {$rol->name}");

            return redirect()
                ->route('configuracion.roles.index')
                ->with('success', 'Rol actualizado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al actualizar rol', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return redirect()
                ->route('configuracion.roles.index')
                ->with('error', 'Error de base de datos al actualizar el rol.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al actualizar rol', ['msg'=>$e->getMessage()]);
            return redirect()
                ->route('configuracion.roles.index')
                ->with('error', 'Ocurrió un error inesperado al actualizar el rol.');
        }
    }

    public function permissions($id)
    {
        $rol = Role::with('permissions:id')->find($id);
        if (!$rol) {
            return redirect()->route('roles.index')
                ->with('error', 'El rol no existe o ya fue eliminado.');
        }

        $permisos = Permission::orderBy('name')->get();
        $permisosSeleccionados = $rol->permissions->pluck('id')->all();

        return view('configuracion.roles.permissions', compact('rol','permisos','permisosSeleccionados'));
    }

    public function assignPermissions(Request $request, $id)
    {
        $rol = Role::find($id);
        if (!$rol) {
            return redirect()->route('configuracion.roles.index')
                ->with('error', 'El rol no existe o ya fue eliminado.');
        }

        $request->validate([
            'permisos'   => 'nullable|array',
            'permisos.*' => 'integer|exists:permissions,id',
        ]);

        try {
            // si no viene nada, limpia todos los permisos
            $rol->syncPermissions($request->permisos ?? []);

            ActividadGeneral::registrar(
                'PERMISOS',
                'roles',
                $rol->id,
                "Actualizó permisos del rol {$rol->name}"
            );

            return redirect()->route('configuracion.roles.index')
                ->with('success', 'Permisos actualizados correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al asignar permisos a rol', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return redirect()->route('configuracion.roles.index')
                ->with('error', 'Error de base de datos al actualizar los permisos.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al asignar permisos a rol', ['msg'=>$e->getMessage()]);
            return redirect()->route('configuracion.roles.index')
                ->with('error', 'Ocurrió un error inesperado al actualizar los permisos.');
        }
    }

    public function destroy($id)
    {
        $rol = Role::withCount('users')->find($id);
        if (!$rol) {
            return redirect()->route('configuracion.roles.index')
                ->with('error', 'El rol no existe o ya fue eliminado.');
        }

        // (Opcional) Control de permisos
        if (!Auth::user()->can('eliminar roles')) {
            return redirect()->route('configuracion.roles.index')
                ->with('error', 'No tienes permiso para eliminar roles.');
        }

        // Evita borrar roles con usuarios asignados
        if ($rol->users_count > 0) {
            return redirect()->route('configuracion.roles.index')
                ->with('error', 'No se puede eliminar un rol con usuarios asignados.');
        }

        try {
            ActividadGeneral::registrar('ELIMINAR', 'roles', $rol->id, "Eliminó el rol {$rol->name}");
            $rol->delete();

            return redirect()->route('configuracion.roles.index')
                ->with('success', 'Rol eliminado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error BD al eliminar rol', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return redirect()->route('configuracion.roles.index')
                ->with('error', 'Error de base de datos al eliminar el rol.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al eliminar rol', ['msg'=>$e->getMessage()]);
            return redirect()->route('configuracion.roles.index')
                ->with('error', 'Ocurrió un error inesperado al eliminar el rol.');
        }
    }
}
