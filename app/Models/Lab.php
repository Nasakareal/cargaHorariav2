<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lab extends Model
{
    protected $table = 'labs';
    protected $primaryKey = 'lab_id';

    // Activar timestamps 
    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'lab_name',
        'description',
        'area',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
        'area'              => 'string',
    ];
}
