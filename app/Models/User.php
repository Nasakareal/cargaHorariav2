<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use Notifiable, HasRoles;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $guard_name = 'web';

    protected $fillable = [
        'nombres',
        'email',
        'password',
        'rol_id',
        'fyh_creacion',
        'fyh_actualizacion',
        'estado',
        'foto_perfil',
        'area',
    ];

    protected $hidden = ['password', 'remember_token'];
    protected $casts = [
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
    ];

    public function getNameAttribute(): string
    {
        return $this->nombres ?: ($this->email ?: 'Usuario');
    }

    public function setPasswordAttribute($value): void
    {
        if (empty($value)) {
            return;
        }

        if (is_string($value) && strlen($value) === 60 && str_starts_with($value, '$2y$')) {
            $this->attributes['password'] = $value;
        } else {
            $this->attributes['password'] = Hash::make($value);
        }
    }

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

    public function adminlte_image(): string
    {
        if (!empty($this->foto_perfil)) {
            $rel = 'uploads/perfiles/' . ltrim($this->foto_perfil, '/');
            if (is_file(public_path($rel))) {
                return asset($rel);
            }
        }

        $max  = 25;
        $seed = $this->id_usuario ?: crc32(strtolower((string)($this->email ?? 'guest')));
        $n    = ((int)$seed % $max) + 1;
        $relAvatar = "img/avatar/avatar-{$n}.png";

        if (is_file(public_path($relAvatar))) {
            return asset($relAvatar);
        }

        return asset('img/default.png');
    }

    public function adminlte_desc(): string
    {
        return $this->area ?: 'Usuario';
    }

    public function adminlte_profile_url()
    {
        return 'perfil';
    }
}
