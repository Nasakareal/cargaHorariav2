<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ManualScheduleAssignment extends Model
{
    protected $table = 'manual_schedule_assignments';
    protected $primaryKey = 'assignment_id';

    // Timestamps personalizados
    public $timestamps = true;
    const CREATED_AT = 'fyh_creacion';
    const UPDATED_AT = 'fyh_actualizacion';

    protected $fillable = [
        'schedule_id',
        'subject_id',
        'teacher_id',
        'group_id',
        'classroom_id',
        'start_time',     // TIME
        'end_time',       // TIME
        'schedule_day',   // 'Lunes'...'Domingo'
        'estado',         // 'activo'|'inactivo'
        'tipo_espacio',   // 'Laboratorio'|'Aula'
        'lab1_assigned',  // ID de lab (entero) si aplica
        'lab2_assigned',  // ID de lab (entero) si aplica
        'fyh_creacion',
        'fyh_actualizacion',
    ];

    protected $casts = [
        'schedule_id'       => 'integer',
        'subject_id'        => 'integer',
        'teacher_id'        => 'integer',
        'group_id'          => 'integer',
        'classroom_id'      => 'integer',
        // OJO: estos son IDs, no booleans
        'lab1_assigned'     => 'integer',
        'lab2_assigned'     => 'integer',
        'fyh_creacion'      => 'datetime',
        'fyh_actualizacion' => 'datetime',
        'estado'            => 'string',
        'tipo_espacio'      => 'string',
        'schedule_day'      => 'string',
    ];

    // ===== Normalización de TIME a H:i:s =====
    protected function startTime(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => self::toHms($v),
        );
    }

    protected function endTime(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => self::toHms($v),
        );
    }

    // ===== Normaliza día (acepta "miercoles" → "Miércoles") =====
    protected function scheduleDay(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => self::canonicalDay($v),
        );
    }

    public static function toHms($time): ?string
    {
        if ($time === null || $time === '') return null;
        // "HH:MM" → "HH:MM:00"
        if (preg_match('/^\d{2}:\d{2}$/', $time)) return $time . ':00';
        return $time;
    }

    public static function canonicalDay(?string $day): ?string
    {
        if ($day === null) return null;
        $k = mb_strtolower(trim($day), 'UTF-8');
        $map = [
            'lunes' => 'Lunes',
            'martes' => 'Martes',
            'miercoles' => 'Miércoles',
            'miércoles' => 'Miércoles',
            'jueves' => 'Jueves',
            'viernes' => 'Viernes',
            'sabado' => 'Sábado',
            'sábado' => 'Sábado',
            'domingo' => 'Domingo',
        ];
        return $map[$k] ?? $day;
    }

    // ===== Relaciones =====
    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id', 'schedule_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'subject_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id', 'teacher_id');
    }

    public function group()
    {
        return $this->belongsTo(Grupo::class, 'group_id', 'group_id');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'classroom_id');
    }

    public function lab1()
    {
        return $this->belongsTo(Lab::class, 'lab1_assigned', 'lab_id');
    }

    public function lab2()
    {
        return $this->belongsTo(Lab::class, 'lab2_assigned', 'lab_id');
    }
}
