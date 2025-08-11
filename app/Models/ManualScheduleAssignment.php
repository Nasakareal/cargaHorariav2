<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualScheduleAssignment extends Model
{
    protected $table = 'manual_schedule_assignments';
    protected $primaryKey = 'assignment_id';

    // Activar timestamps usando columnas personalizadas
    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'schedule_id',
        'subject_id',
        'teacher_id',
        'group_id',
        'classroom_id',
        'start_time',
        'end_time',
        'schedule_day',
        'estado',
        'tipo_espacio',
        'lab1_assigned',
        'lab2_assigned',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'schedule_id'       => 'integer',
        'subject_id'        => 'integer',
        'teacher_id'        => 'integer',
        'group_id'          => 'integer',
        'classroom_id'      => 'integer',
        'start_time'        => 'datetime:H:i',
        'end_time'          => 'datetime:H:i',
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
        'lab1_assigned'     => 'boolean',
        'lab2_assigned'     => 'boolean',
        'estado'            => 'string',
        'tipo_espacio'      => 'string',
    ];

    // Relaciones opcionales
    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id', 'schedule_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'id_materia');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id', 'teacher_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'classroom_id');
    }
}
