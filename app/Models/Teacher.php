<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $table = 'teachers';
    protected $primaryKey = 'teacher_id';
    public $timestamps = false;

    protected $fillable = [
        'teacher_name',
        'puesto',
        'hours',
        'specialization_area',
        'specialization_program_id',
        'program_id',
        'area',
        'clasificacion',
        'fyh_creacion',
        'fyh_actualizacion',
        'estado',
    ];

    protected $casts = [
        'hours'                     => 'integer',
        'specialization_program_id' => 'integer',
        'program_id'                => 'integer',
    ];

    // ===== Relaciones
    public function scheduleAssignments()
    { return $this->hasMany(ScheduleAssignment::class, 'teacher_id', 'teacher_id'); }

    public function manualScheduleAssignments()
    { return $this->hasMany(ManualScheduleAssignment::class, 'teacher_id', 'teacher_id'); }

    public function materias()
    {
        return $this->belongsToMany(Subject::class, 'teacher_subjects', 'teacher_id', 'subject_id')
                    ->withPivot('group_id');
    }
}
