<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $table = 'schedules';
    protected $primaryKey = 'schedule_id';

    // Activar timestamps personalizados
    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'teacher_subject_id',
        'classroom_id',
        'schedule_day',
        'start_time',
        'end_time',
        'estado',
        'group_id',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'teacher_subject_id' => 'integer',
        'classroom_id'       => 'integer',
        'group_id'           => 'integer',
        'start_time'         => 'datetime:H:i',
        'end_time'           => 'datetime:H:i',
        'fyh_creacion'       => 'datetime',
        'fyh_actualizacion'  => 'datetime',
        'estado'             => 'string',
        'schedule_day'       => 'string',
    ];

    // Relaciones
    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'classroom_id');
    }

    public function group()
    {
        return $this->belongsTo(Grupo::class, 'group_id', 'group_id');
    }

    public function teacherSubject()
    {
        return $this->belongsTo(TeacherSubject::class, 'teacher_subject_id', 'teacher_subject_id');
    }
}
