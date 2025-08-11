<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionInstitucion extends Model
{
    protected $table = 'configuracion_instituciones';
    protected $primaryKey = 'id_config_institucion';

    // Activar timestamps usando tus columnas personalizadas
    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'nombre_institucion',
        'logo',
        'direccion',
        'telefono',
        'celular',
        'correo',
        'estado',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
        'estado'            => 'string',
    ];
}
