<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupSubject extends Model
{
    protected $table = 'group_subjects';
    protected $primaryKey = 'group_subject_id';

    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'group_id',
        'subject_id',
        'estado',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'group_id'          => 'integer',
        'subject_id'        => 'integer',
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
        'estado'            => 'string',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'id_grupo');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'id_materia');
    }
}
