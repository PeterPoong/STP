<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_transcript extends Model
{
    use HasFactory;

    public function subject()
    {
        return $this->hasOne(stp_subject::class, 'id', 'subject_id');
    }

    public function grade()
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'transcript_grade');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
