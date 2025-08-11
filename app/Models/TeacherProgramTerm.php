<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherProgramTerm extends Model
{
    protected $table = 'teacher_program_term';
    protected $primaryKey = 'teacher_program_term_id';

    public $timestamps = false;

    protected $fillable = [
        'teacher_id',
        'program_id',
        'term_id',
        'fyh_creacion',
        'fyh_actualizacion',
        'estado',
    ];

    protected $casts = [
        'teacher_id' => 'integer',
        'program_id' => 'integer',
        'term_id'    => 'integer',
    ];
}
