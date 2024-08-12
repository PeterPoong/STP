<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function subject()
    {
        return $this->hasOne(stp_subject::class, 'id', 'subject_id');
    }

    public function grade()
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'transcript_grade');
    }

    public function student()
    {
        return $this->hasOne(User::class, 'id', 'student_id');
    }
}
