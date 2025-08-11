<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupScheduleTeacher extends Model
{
    protected $table = 'group_schedule_teacher';
    protected $primaryKey = 'group_schedule_teacher_id';

    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'schedule_id',
        'teacher_id',
        'subject_id',
        'estado',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'schedule_id'       => 'integer',
        'teacher_id'        => 'integer',
        'subject_id'        => 'integer',
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
        'estado'            => 'string',
    ];

    // Relaciones
    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id', 'schedule_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id', 'teacher_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'id_materia');
    }
}
