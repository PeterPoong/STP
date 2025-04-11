<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class stp_user_media extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(stp_student::class, 'student_id', 'id');
    }


    public function type()
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'userMedia_type');
    }
}
