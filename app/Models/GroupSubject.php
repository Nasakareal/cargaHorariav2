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
        'estado'            => 'string',
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
    ];

    // ===== Relaciones (ojo con los owner keys correctos)
    public function group()
    { return $this->belongsTo(Grupo::class, 'group_id', 'group_id'); }

    public function subject()
    { return $this->belongsTo(Subject::class, 'subject_id', 'subject_id'); }
}
