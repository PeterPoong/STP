<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_user_otp extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'otp',
        'otp_expired_time',
        'otp_status'
    ];

    public function userOtp(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
