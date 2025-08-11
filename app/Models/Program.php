<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $table = 'programs';
    protected $primaryKey = 'program_id';

    // Timestamps personalizados
    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'program_name',
        'area',
        'estado',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
        'area'              => 'string',
        'estado'            => 'string',
    ];

    // Relaciones
    public function grupos()
    {
        return $this->hasMany(Grupo::class, 'program_id', 'program_id');
    }
}
