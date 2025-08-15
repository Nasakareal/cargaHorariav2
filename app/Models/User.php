<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasRoles;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public $incrementing = true;
    protected $keyType = 'int';

    /** Timestamps personalizados */
    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    /** Guard para Spatie */
    protected $guard_name = 'web';

    protected $fillable = [
        'nombres',
        'email',
        'password',
        'rol_id',
        'fyh_creacion',
        'fyh_actualizacion',
        'estado',
        'foto_perfil',   // puede ser "avatar-#.png" o un nombre de archivo subido
        'area',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
    ];

    /** ===== nombre mostrado (AdminLTE usa ->name) ===== */
    public function getNameAttribute(): string
    {
        return $this->nombres ?: ($this->email ?: 'Usuario');
    }

    /** ===== Hash automático del password ===== */
    public function setPasswordAttribute($value): void
    {
        if (empty($value)) return;

        if (is_string($value) && strlen($value) === 60 && str_starts_with($value, '$2y$')) {
            $this->attributes['password'] = $value; // ya viene hasheado
        } else {
            $this->attributes['password'] = Hash::make($value);
        }
    }

    /** ===== Nombre del rol (con Spatie o fallback por rol_id) ===== */
    public function getRolNombreAttribute(): string
    {
        $bySpatie = optional($this->roles->first())->name;
        if ($bySpatie) return $bySpatie;

        if ($this->rol_id) {
            $role = \Spatie\Permission\Models\Role::find($this->rol_id);
            if ($role) return $role->name;
        }
        return 'Sin rol';
    }

    /** ===== Imagen para el menú de usuario de AdminLTE ===== */
    public function adminlte_image(): string
    {
        // Cache-buster para que el navegador NO guarde la versión anterior
        $ver = optional($this->fyh_actualizacion)->timestamp
            ?? optional($this->fyh_creacion)->timestamp
            ?? time();

        // 1) Si guardaste uno de los avatares predefinidos (p.ej. "avatar-10.png")
        if (!empty($this->foto_perfil) && str_starts_with($this->foto_perfil, 'avatar-')) {
            return asset('img/avatar/'.$this->foto_perfil) . '?v=' . $ver;
        }

        // 2) Si hay archivo subido en public/uploads/perfiles
        if (!empty($this->foto_perfil)) {
            return asset('uploads/perfiles/' . ltrim($this->foto_perfil, '/')) . '?v=' . $ver;
        }

        // 3) Fallback determinístico 1..25
        $max  = 25;
        $seed = $this->id_usuario ?: crc32(strtolower((string)($this->email ?? 'guest')));
        $n    = ((int)$seed % $max) + 1;
        return asset("img/avatar/avatar-{$n}.png") . '?v=' . $ver;
    }

    /** ===== Descripción bajo el nombre en el menú ===== */
    public function adminlte_desc(): string
    {
        return $this->area ?: 'Usuario';
    }

    /** ===== URL del perfil en el menú ===== */
    public function adminlte_profile_url()
    {
        return route('perfil.index');
    }
}
