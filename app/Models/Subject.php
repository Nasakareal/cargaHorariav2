<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $table = 'subjects';
    protected $primaryKey = 'subject_id';

    // Timestamps personalizados
    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'subject_name',
        'weekly_hours',
        'max_consecutive_class_hours',
        'program_id',
        'term_id',
        'unidades',
        'estado',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'subject_name'                  => 'string',
        'weekly_hours'                  => 'integer',
        'max_consecutive_class_hours'   => 'integer',
        'program_id'                    => 'integer',
        'term_id'                       => 'integer',
        'unidades'                      => 'integer',
        'estado'                        => 'string',
        'fyh_creacion'                  => 'datetime',
        'fyh_actualizacion'             => 'datetime',
    ];
}
