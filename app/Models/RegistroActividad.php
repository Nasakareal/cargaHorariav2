<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistroActividad extends Model
{
    protected $table = 'registro_actividad';
    public $timestamps = false;

    protected $fillable = [
        'email',
        'status',
        'ip',
        'fecha',
    ];
}
