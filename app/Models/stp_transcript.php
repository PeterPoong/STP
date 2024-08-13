<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class stp_transcript extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'transcript_grade',
        'student_id',
        'transcript_category',
        'transcript_status',
        'updated_by',
        'created_by'
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(stp_subject::class, 'subject_id', 'id');
    }

    public function grade()
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'transcript_grade');
    }

    public function category(): HasOne
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'transcript_category');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(stp_student::class, 'student_id', 'id');
    }
}
