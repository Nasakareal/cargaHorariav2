<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarioEscolar extends Model
{
    protected $table = 'calendario_escolar';
    protected $primaryKey = 'id';

    // Si quieres que Laravel maneje fyh_creacion y fyh_actualizacion como timestamps:
    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'nombre_cuatrimestre',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'fecha_inicio'      => 'date',
        'fecha_fin'         => 'date',
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
        'estado'            => 'string',
    ];
}
