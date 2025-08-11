<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    protected $table = 'terms';
    protected $primaryKey = 'term_id';

    public $timestamps = false;

    protected $fillable = [
        'term_name',
        'fyh_creacion',
        'fyh_actualizacion',
        'estado',
    ];

    protected $casts = [
        'term_name' => 'string',
        'fyh_creacion' => 'datetime',
        'fyh_actualizacion' => 'datetime',
    ];
}
