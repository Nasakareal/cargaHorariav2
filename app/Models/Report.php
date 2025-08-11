<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $table = 'reports';
    protected $primaryKey = 'report_id';

    // Usa los timestamps nativos de Laravel
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'admin_id',
        'report_message',
        'is_read',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'user_id'      => 'integer',
        'admin_id'     => 'integer',
        'is_read'      => 'boolean',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    // Relaciones opcionales
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id_usuario');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id', 'id_usuario');
    }
}
