<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuildingProgram extends Model
{
    protected $table = 'building_programs';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'area',
        'planta_alta',
        'planta_baja',
        'fyh_creacion',
        'fyh_actualizacion',
        'building_name',
    ];
}
