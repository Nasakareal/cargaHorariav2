<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $table = 'groups';
    protected $primaryKey = 'group_id';

    // Activar timestamps
    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'group_name',
        'program_id',
        'area',
        'term_id',
        'volume',
        'turn_id',
        'classroom_assigned',
        'lab_assigned',
        'estado',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'program_id'        => 'integer',
        'term_id'           => 'integer',
        'volume'            => 'integer',
        'turn_id'           => 'integer',
        'classroom_assigned'=> 'integer',
        'lab_assigned'      => 'integer',
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
        'estado'            => 'string',
    ];

    // Relaciones 
    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id', 'program_id');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_assigned', 'classroom_id');
    }

    public function laboratory()
    {
        return $this->belongsTo(Classroom::class, 'lab_assigned', 'classroom_id');
    }
}
