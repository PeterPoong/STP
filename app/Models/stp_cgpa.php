<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_cgpa extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'program_name',
        'transcript_category',
        'cgpa',
        'cgpa_status',
        'created_by',
        'updated_by'
    ];

    public function student(): BelongsTo
    {
        return $this->belongTo(stp_student::class, 'student_id', 'id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'transcript_category', 'id');
    }
}
