<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Notifiable, HasRoles;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';

    /** 🔹 Timestamps personalizados */
    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    /** 🔹 Guard para Spatie */
    protected $guard_name = 'web';

    protected $fillable = [
        'nombres',
        'email',
        'password',
        'rol_id', // si lo usas para “reflejar” el rol principal
        'fyh_creacion',
        'fyh_actualizacion',
        'estado',
        'foto_perfil',
        'area',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
    ];

    /** =========================
     *  Accesor “rol_nombre” basado en Spatie
     *  ========================= */
    public function getRolNombreAttribute()
    {
        // 1) Rol por Spatie (pivot)
        $bySpatie = optional($this->roles->first())->name;
        if ($bySpatie) return $bySpatie;

        // 2) Fallback: si guardas rol_id en usuarios, intenta resolver con Spatie por id
        if ($this->rol_id) {
            $role = \Spatie\Permission\Models\Role::find($this->rol_id);
            if ($role) return $role->name;
        }

        return 'Sin rol';
    }

    /** =========================
     *  AdminLTE helpers
     *  ========================= */
    public function adminlte_image()
    {
        return $this->foto_perfil
            ? asset('uploads/perfiles/'.$this->foto_perfil)
            : asset('img/default.png');
    }

    public function adminlte_desc()
    {
        return $this->area ?: 'Usuario';
    }

    public function adminlte_profile_url()
    {
        return route('perfil.index');
    }
}
