<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramTermSubject extends Model
{
    protected $table = 'program_term_subjects';
    protected $primaryKey = 'program_term_subject_id';

    public $timestamps = false;

    protected $fillable = [
        'program_id',
        'term_id',
        'subject_id',
    ];

    protected $casts = [
        'program_id' => 'integer',
        'term_id'    => 'integer',
        'subject_id' => 'integer',
    ];

    // Relaciones opcionales
    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id', 'program_id');
    }

    public function term()
    {
        return $this->belongsTo(Term::class, 'term_id', 'term_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'id_materia');
    }
}
