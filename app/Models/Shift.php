<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $table = 'shifts';
    protected $primaryKey = 'shift_id';

    // Timestamps personalizados
    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'shift_name',
        'schedule_details',
        'estado',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'shift_name'        => 'string',
        'schedule_details'  => 'string',
        'estado'            => 'string',
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
    ];
}
