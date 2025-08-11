<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Models\ActividadGeneral;

class UsuarioController extends Controller
{
    public function index()
    {
        // Trae roles de Spatie para que el accesor funcione sin N+1
        $usuarios = User::with('roles')->orderBy('id_usuario')->get();
        return view('configuracion.usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();

        // Solo áreas únicas y ordenadas
        $areas = DB::table('programs')
                ->whereNotNull('area')
                ->distinct()
                ->orderBy('area')
                ->pluck('area');

        return view('configuracion.usuarios.create', compact('roles', 'areas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombres'  => 'required|string|max:255',
            'email'    => 'required|email|unique:usuarios,email',
            'password' => 'required|min:6|confirmed',
            'rol_id'   => 'required|integer|exists:roles,id',
            'areas'    => 'nullable|array',
            'areas.*'  => 'string|max:255',
        ], [
            'nombres.required' => 'El nombre del usuario es obligatorio.',
            'email.required'   => 'El correo electrónico es obligatorio.',
            'email.email'      => 'El correo electrónico no tiene un formato válido.',
            'email.unique'     => 'Ese correo electrónico ya está registrado.',
            'password.required'=> 'La contraseña es obligatoria.',
            'password.min'     => 'La contraseña debe tener al menos :min caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'rol_id.required'  => 'Debe seleccionar un rol.',
        ], [
            'nombres' => 'nombre del usuario',
            'email'   => 'correo electrónico',
            'password'=> 'contraseña',
            'rol_id'  => 'rol',
            'areas'   => 'áreas',
        ]);

        try {
            $role = Role::findOrFail($request->rol_id);

            $areasStr = $request->filled('areas')
                ? implode(',', $request->areas)
                : null; // <- importante

            $usuario = User::create([
                'nombres'          => $request->nombres,
                'email'            => $request->email,
                'password'         => Hash::make($request->password),
                'area'             => $areasStr,
                'estado'           => 'ACTIVO',
                'rol_id'           => $role->id,
                'fyh_creacion'     => now(),
                'fyh_actualizacion'=> null,
            ]);

            ActividadGeneral::registrar('CREAR', 'usuarios', $usuario->id_usuario, "Creó al usuario {$usuario->email}");

            $usuario->assignRole($role);

            return redirect()
                ->route('configuracion.usuarios.index')
                ->with('success', 'Usuario creado correctamente.');
        } catch (QueryException $e) {
            Log::error('Error de BD al crear usuario', ['code'=>$e->getCode(),'msg'=>$e->getMessage()]);
            return redirect()
                ->route('configuracion.usuarios.index')
                ->with('error', 'Error de base de datos al crear el usuario.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al crear usuario', ['msg'=>$e->getMessage()]);
            return redirect()
                ->route('configuracion.usuarios.index')
                ->with('error', 'Ocurrió un error inesperado al crear el usuario.');
        }
    }

    public function show($id)
    {
        $usuario = User::with('roles')->findOrFail($id);
        return view('configuracion.usuarios.show', compact('usuario'));
    }

    public function edit($id)
    {
        $usuario = User::with('roles')->findOrFail($id);
        $roles   = Role::orderBy('name')->get();

        // áreas únicas desde programs
        $areas   = DB::table('programs')
            ->whereNotNull('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        // áreas actuales del usuario (array)
        $usuarioAreas = $usuario->area
            ? array_filter(array_map('trim', explode(',', $usuario->area)))
            : [];

        return view('configuracion.usuarios.edit', compact('usuario', 'roles', 'areas', 'usuarioAreas'));
    }

    public function update(Request $request, $id)
    {
        try {
            $usuario = User::find($id);

            if (!$usuario) {
                return redirect()
                    ->route('configuracion.usuarios.index')
                    ->with('error', 'El usuario no existe o ya fue eliminado.');
            }

            $request->validate([
                'nombres'  => 'required|string|max:255',
                'email'    => 'required|email|unique:usuarios,email,' . $usuario->id_usuario . ',id_usuario',
                'password' => 'nullable|min:6|confirmed', // <- opcional
                'rol_id'   => 'required|integer|exists:roles,id',
                'areas'    => 'nullable|array',
                'areas.*'  => 'string|max:255',
                'estado'   => 'required|string|max:11',
            ], [
                'nombres.required' => 'El nombre del usuario es obligatorio.',
                'email.required'   => 'El correo electrónico es obligatorio.',
                'email.email'      => 'El correo electrónico no tiene un formato válido.',
                'email.unique'     => 'Ese correo electrónico ya está registrado.',
                'password.min'     => 'La contraseña debe tener al menos :min caracteres.',
                'password.confirmed' => 'Las contraseñas no coinciden.',
                'rol_id.required'  => 'Debe seleccionar un rol.',
                'estado.required'  => 'El estado es obligatorio.',
            ], [
                'nombres' => 'nombre del usuario',
                'email'   => 'correo electrónico',
                'password'=> 'contraseña',
                'rol_id'  => 'rol',
                'areas'   => 'áreas',
                'estado'  => 'estado',
            ]);

            $role = Role::findOrFail($request->rol_id);

            $usuario->nombres = $request->nombres;
            $usuario->email   = $request->email;
            $usuario->area    = $request->filled('areas')
                ? implode(',', $request->areas)
                : null; // <- evita error si no hay áreas
            $usuario->estado  = $request->estado;
            $usuario->rol_id  = $role->id;

            if ($request->filled('password')) {
                $usuario->password = Hash::make($request->password);
            }

            $usuario->fyh_actualizacion = now();
            $usuario->save();

            ActividadGeneral::registrar('ACTUALIZAR', 'usuarios', $usuario->id_usuario, "Actualizó al usuario {$usuario->email}");

            $usuario->syncRoles([$role]);

            return redirect()
                ->route('configuracion.usuarios.index')
                ->with('success', 'Usuario actualizado correctamente.');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()
                ->route('configuracion.usuarios.index')
                ->with('error', 'Error de base de datos al actualizar el usuario: ' . $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()
                ->route('configuracion.usuarios.index')
                ->with('error', 'Ocurrió un error inesperado al actualizar el usuario.');
        }
    }

    public function destroy($id)
    {
        // 1) Existe
        $usuario = User::find($id);
        if (!$usuario) {
            return redirect()
                ->route('configuracion.usuarios.index')
                ->with('error', 'El usuario no existe o ya fue eliminado.');
        }

        // 2) Permiso explícito (además del middleware/spatie)
        if (!Auth::user()->can('eliminar usuarios')) {
            return redirect()
                ->route('configuracion.usuarios.index')
                ->with('error', 'No tienes permiso para eliminar usuarios.');
        }

        // 3) No permitir auto-eliminarse
        if ((int) Auth::id() === (int) $usuario->id_usuario) {
            return redirect()
                ->route('configuracion.usuarios.index')
                ->with('error', 'No puedes eliminar tu propio usuario.');
        }

        try {

            ActividadGeneral::registrar('ELIMINAR', 'usuarios', $usuario->id_usuario, "Eliminó al usuario {$usuario->email}");

            $usuario->delete();

            return redirect()
                ->route('configuracion.usuarios.index')
                ->with('success', 'Usuario eliminado correctamente.');
        } catch (QueryException $e) {
            // 4) FK/relaciones (SQLSTATE 23000)
            if ($e->getCode() === '23000') {
                return redirect()
                    ->route('configuracion.usuarios.index')
                    ->with('error', 'No se puede eliminar porque tiene registros relacionados (restricción de integridad).');
            }

            Log::error('Error de BD al eliminar usuario', [
                'usuario_id' => $id,
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('configuracion.usuarios.index')
                ->with('error', 'Error de base de datos al eliminar el usuario.');
        } catch (\Throwable $e) {
            Log::error('Error inesperado al eliminar usuario', [
                'usuario_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('configuracion.usuarios.index')
                ->with('error', 'Ocurrió un error inesperado al eliminar el usuario.');
        }
    }
}
