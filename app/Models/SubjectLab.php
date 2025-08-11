<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectLab extends Model
{
    protected $table = 'subject_labs';
    protected $primaryKey = 'subject_lab_id';

    public $timestamps = false;

    protected $fillable = [
        'subject_id',
        'lab_id',
        'lab_hours',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'lab_id'     => 'integer',
        'lab_hours'  => 'integer',
    ];
}
