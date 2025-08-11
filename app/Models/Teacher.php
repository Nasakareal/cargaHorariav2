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
        'hours'                      => 'integer',
        'specialization_program_id'  => 'integer',
        'program_id'                 => 'integer',
    ];

    // Relación con materias (subjects)
    public function materias()
    {
        // Pivot sugerido: subject_teacher (subject_id, teacher_id)
        return $this->belongsToMany(Subject::class, 'subject_teacher', 'teacher_id', 'subject_id');
    }
}
