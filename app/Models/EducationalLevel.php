<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EducationalLevel extends Model
{
    protected $table = 'educational_levels';
    protected $primaryKey = 'level_id';

    public $timestamps = false;

    protected $fillable = [
        'level_name',
        'group_id',
    ];

    protected $casts = [
        'group_id' => 'integer',
        'level_name' => 'string',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'id_grupo');
    }
}
