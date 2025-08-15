<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $table = 'groups';
    protected $primaryKey = 'group_id';

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
        'program_id'         => 'integer',
        'term_id'            => 'integer',
        'volume'             => 'integer',
        'turn_id'            => 'integer',
        'classroom_assigned' => 'integer',
        'lab_assigned'       => 'integer',
        'fyh_creacion'       => 'datetime',
        'fyh_actualizacion'  => 'datetime',
        'estado'             => 'string',
    ];

    // ===== Scopes
    public function scopeActivos($q) { return $q->where('estado', '1'); }

    // ===== Relaciones
    public function program()   { return $this->belongsTo(Program::class,   'program_id',         'program_id'); }
    public function term()      { return $this->belongsTo(Term::class,      'term_id',            'term_id'); }
    public function classroom() { return $this->belongsTo(Classroom::class, 'classroom_assigned', 'classroom_id'); }
    public function lab()       { return $this->belongsTo(Lab::class,       'lab_assigned',       'lab_id'); }

    public function scheduleAssignments()
    { return $this->hasMany(ScheduleAssignment::class, 'group_id', 'group_id'); }

    public function manualScheduleAssignments()
    { return $this->hasMany(ManualScheduleAssignment::class, 'group_id', 'group_id'); }

    public function subjects()
    { return $this->belongsToMany(Subject::class, 'group_subjects', 'group_id', 'subject_id'); }
}
