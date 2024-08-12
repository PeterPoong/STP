<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_school_otp extends Model
{
    use HasFactory;
    protected $fillable = [
        'school_id',
        'otp',
        'otp_expired_time',
        'otp_status'
    ];

    public function schoolOtp(): BelongsTo
    {
        return $this->belongsTo(stp_school::class, 'school_id', 'id');
    }
}
