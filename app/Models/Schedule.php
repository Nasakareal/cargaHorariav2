<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $table = 'schedules';
    protected $primaryKey = 'schedule_id';

    // Timestamps personalizados
    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'teacher_subject_id',
        'classroom_id',
        'group_id',
        'schedule_day',
        'start_time',
        'end_time',
        'estado',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'teacher_subject_id' => 'integer',
        'classroom_id'       => 'integer',
        'group_id'           => 'integer',
        'schedule_day'       => 'string',
        'start_time'         => 'datetime:H:i:s',
        'end_time'           => 'datetime:H:i:s',
        'estado'             => 'string',
        'fyh_creacion'       => 'datetime',
        'fyh_actualizacion'  => 'datetime',
    ];

    // ===== Scopes
    public function scopeActivos($q) { return $q->where('estado', 'activo'); }

    // ===== Relaciones
    public function classroom()
    { return $this->belongsTo(Classroom::class, 'classroom_id', 'classroom_id'); }

    public function group()
    { return $this->belongsTo(Grupo::class, 'group_id', 'group_id'); }

    public function teacherSubject()
    { return $this->belongsTo(TeacherSubject::class, 'teacher_subject_id', 'teacher_subject_id'); }

    // Enlace con las asignaciones oficiales (schedule_assignments) que referencian esta cabecera
    public function assignments()
    { return $this->hasMany(ScheduleAssignment::class, 'schedule_id', 'schedule_id'); }

    // (Opcional) Enlace con manual si también usa schedule_id (en tu dump salía NULL, pero la relación no estorba)
    public function manualAssignments()
    { return $this->hasMany(ManualScheduleAssignment::class, 'schedule_id', 'schedule_id'); }
}
