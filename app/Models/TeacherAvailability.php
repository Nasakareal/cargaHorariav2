<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherAvailability extends Model
{
    protected $table = 'teacher_availability';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'teacher_id' => 'integer',
        'start_time' => 'string',
        'end_time'   => 'string',
    ];
}
