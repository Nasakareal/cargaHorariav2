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
        if (empty($value)) return;

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
        $ver = optional($this->fyh_actualizacion)->timestamp
            ?? optional($this->fyh_creacion)->timestamp
            ?? time();

        $foto = (string)($this->foto_perfil ?? '');

        if ($foto && (str_starts_with($foto, 'http://') || str_starts_with($foto, 'https://') || str_starts_with($foto, '//'))) {
            return $foto.(str_contains($foto,'?') ? '&' : '?').'v='.$ver;
        }

        if ($foto && str_starts_with($foto, 'avatar-')) {
            $file = str_contains($foto, '.') ? $foto : ($foto.'.jpg');
            return asset('dist/img/avatar/'.$file).'?v='.$ver;
        }

        if ($foto) {
            $p = ltrim($foto, '/');

            if (str_starts_with($p, 'public/')) {
                $p = substr($p, 7);
            }

            if (!str_starts_with($p, 'uploads/perfiles/')) {
                $p = 'uploads/perfiles/'.$p;
            }

            return asset($p).'?v='.$ver;
        }

        $max  = 25;
        $seed = $this->id_usuario ?: crc32(strtolower((string)($this->email ?? 'guest')));
        $n    = ((int)$seed % $max) + 1;
        return asset("dist/img/avatar/avatar-{$n}.jpg").'?v='.$ver;
    }

    public function adminlte_desc(): string
    {
        return $this->area ?: 'Usuario';
    }

    public function adminlte_profile_url()
    {
        return route('perfil.index');
    }
}
