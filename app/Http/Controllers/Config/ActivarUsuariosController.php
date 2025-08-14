<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use App\Models\ActividadGeneral;

class ActivarUsuariosController extends Controller
{
    private const ESTADO_ACTIVO   = 'ACTIVO';
    private const ESTADO_INACTIVO = 'Inactivo';

    public function activarTodos()    { return $this->toggle(true); }
    public function desactivarTodos() { return $this->toggle(false); }

    private function toggle(bool $activar)
    {
        if (!Auth::user()?->can('activar usuarios')) abort(403);

        try {
            DB::beginTransaction();

            $adminRoleIds = Role::whereIn('name', ['ADMINISTRADOR','Administrador','ADMIN','Admin'])->pluck('id');
            $adminUserIds = DB::table('model_has_roles')
                ->when($adminRoleIds->isNotEmpty(), fn($q)=>$q->whereIn('role_id',$adminRoleIds))
                ->where('model_type', User::class)
                ->pluck('model_id');

            $q = DB::table('users')
                ->when($adminUserIds->isNotEmpty(), fn($qq)=>$qq->whereNotIn('id',$adminUserIds))
                ->where(function ($w) { $w->whereNull('area')->orWhere('area','<>','ADMIN'); });

            $afectados = $q->update([
                'estado'     => $activar ? self::ESTADO_ACTIVO : self::ESTADO_INACTIVO,
                'updated_at' => now(),
            ]);

            ActividadGeneral::registrar(
                $activar ? 'ACTIVAR_USUARIOS' : 'DESACTIVAR_USUARIOS',
                'users', null,
                ($activar ? 'Activó' : 'Desactivó') . " {$afectados} usuario(s) (excluyendo ADMIN)."
            );

            DB::commit();
            return redirect()->route('configuracion.index')->with('success',
                $activar ? 'Usuarios activados.' : 'Usuarios desactivados.'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Toggle usuarios', ['msg'=>$e->getMessage()]);
            return back()->with('error','No se pudo actualizar el estado de los usuarios.');
        }
    }
}
