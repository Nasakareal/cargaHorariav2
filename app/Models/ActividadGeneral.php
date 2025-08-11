<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActividadGeneral extends Model
{
    protected $table = 'actividad_general';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'nombre_usuario',
        'accion',
        'tabla',
        'registro_id',
        'descripcion',
        'fecha',
    ];

    public static function registrar(string $accion, string $tabla, $registroId = null, ?string $descripcion = null): void
    {
        $u = auth()->user();
        static::create([
            'usuario_id'     => $u->id_usuario ?? null,
            'nombre_usuario' => $u->nombres    ?? 'Sistema',
            'accion'         => strtoupper($accion),
            'tabla'          => $tabla,
            'registro_id'    => $registroId,
            'descripcion'    => $descripcion,
            'fecha'          => now(),
        ]);
    }
}
