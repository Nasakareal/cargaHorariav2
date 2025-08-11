<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherSubject extends Model
{
    protected $table = 'teacher_subjects';
    protected $primaryKey = 'teacher_subject_id';

    public $timestamps = false;

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'group_id',
        'fyh_creacion',
        'fyh_actualizacion',
        'estado',
    ];

    protected $casts = [
        'teacher_id' => 'integer',
        'subject_id' => 'integer',
        'group_id'   => 'integer',
    ];
}
