<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassroomState extends Model
{
    protected $table = 'classroom_states';
    protected $primaryKey = 'state_id';

    // Usa los timestamps nativos (created_at / updated_at) de tu tabla
    public $timestamps = true;

    protected $fillable = [
        'classroom_id',
        'state',
        'description',
        'created_at',
        'updated_at',
    ]

    protected $casts = [
        'classroom_id' => 'integer',
        'state'        => 'string',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    // Relación: cada estado pertenece a un aula
    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'classroom_id');
    }
}
