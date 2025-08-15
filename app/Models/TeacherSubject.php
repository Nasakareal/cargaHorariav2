<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherSubject extends Model
{
    protected $table = 'teacher_subjects';
    protected $primaryKey = 'teacher_subject_id';

    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'group_id',
        'estado',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'teacher_id'        => 'integer',
        'subject_id'        => 'integer',
        'group_id'          => 'integer',
        'estado'            => 'string',
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
    ];

    // ===== Relaciones
    public function teacher()
    { return $this->belongsTo(Teacher::class, 'teacher_id', 'teacher_id'); }

    public function subject()
    { return $this->belongsTo(Subject::class, 'subject_id', 'subject_id'); }

    public function group()
    { return $this->belongsTo(Grupo::class, 'group_id', 'group_id'); }

    // Relación con cabecera de horario y con asignaciones oficiales
    public function schedules()
    { return $this->hasMany(Schedule::class, 'teacher_subject_id', 'teacher_subject_id'); }

    public function scheduleAssignments()
    { return $this->hasMany(ScheduleAssignment::class, 'teacher_id', 'teacher_id'); } // útil para consultas rápidas
}
