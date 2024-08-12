<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_student_otp extends Model
{
    use HasFactory;
    protected $fillable = [
        'student_id',
        'otp',
        'otp_expired_time',
        'otp_status'
    ];

    public function studentOtp(): BelongsTo
    {
        return $this->belongsTo(stp_student::class, 'student_id', 'id');
    }
}
