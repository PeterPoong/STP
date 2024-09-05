<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_higher_transcript extends Model
{
    protected $fillable = [
        'highTranscript_name',
        'category_id',
        'student_id',
        'higherTranscript_grade',
        'highTranscript_status',
        'updated_by',
        'created_by'
    ];

    use HasFactory;

    public function category(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'category_id', 'id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(stp_student::class, 'student_id', 'id');
    }
}
