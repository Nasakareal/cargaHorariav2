<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherGroup extends Model
{
    protected $table = 'teacher_groups';
    protected $primaryKey = 'teacher_group_id';

    public $timestamps = false;

    protected $fillable = [
        'teacher_id',
        'group_id',
        'fyh_creacion',
    ];

    protected $casts = [
        'teacher_id' => 'integer',
        'group_id'   => 'integer',
    ];
}
