<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $table = 'classrooms';
    protected $primaryKey = 'classroom_id';

    // Activar timestamps usando tus columnas personalizadas
    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'classroom_name',
        'capacity',
        'building',
        'floor',
        'estado',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'capacity'         => 'integer',
        'fyh_creacion'     => 'datetime',
        'fyh_actualizacion'=> 'datetime',
        'estado'           => 'string',
    ];
}
