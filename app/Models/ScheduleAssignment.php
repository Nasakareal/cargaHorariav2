<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleAssignment extends Model
{
    protected $table = 'schedule_assignments';
    protected $primaryKey = 'assignment_id';

    // Activar timestamps personalizados
    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'schedule_id',
        'subject_id',
        'teacher_id',
        'group_id',
        'classroom_id',
        'lab_id',
        'start_time',
        'end_time',
        'schedule_day',
        'estado',
        'tipo_espacio',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'schedule_id'       => 'integer',
        'subject_id'        => 'integer',
        'teacher_id'        => 'integer',
        'group_id'          => 'integer',
        'classroom_id'      => 'integer',
        'lab_id'            => 'integer',
        'start_time'        => 'datetime:H:i',
        'end_time'          => 'datetime:H:i',
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
        'estado'            => 'string',
        'tipo_espacio'      => 'string',
        'schedule_day'      => 'string',
    ];

    // Relaciones
    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id', 'schedule_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'subject_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id', 'teacher_id');
    }

    public function group()
    {
        return $this->belongsTo(Grupo::class, 'group_id', 'group_id');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'classroom_id');
    }

    public function lab()
    {
        return $this->belongsTo(Lab::class, 'lab_id', 'lab_id');
    }
}
