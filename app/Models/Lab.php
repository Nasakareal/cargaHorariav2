<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lab extends Model
{
    protected $table = 'labs';
    protected $primaryKey = 'lab_id';

    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'lab_name',
        'description',
        'area',
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
        'area'              => 'string',
    ];

    // ===== Relaciones
    public function scheduleAssignments()
    { return $this->hasMany(ScheduleAssignment::class, 'lab_id', 'lab_id'); }

    // Manual usa lab1_assigned / lab2_assigned con IDs de lab
    public function manualAsLab1()
    { return $this->hasMany(ManualScheduleAssignment::class, 'lab1_assigned', 'lab_id'); }

    public function manualAsLab2()
    { return $this->hasMany(ManualScheduleAssignment::class, 'lab2_assigned', 'lab_id'); }

    public function groups()
    { return $this->hasMany(Grupo::class, 'lab_assigned', 'lab_id'); }

    // Helper: verificar si el lab soporta un área
    public function belongsToArea(string $area): bool
    {
        $areas = array_filter(array_map('trim', explode(',', (string) $this->area)));
        return in_array($area, $areas, true);
    }
}
