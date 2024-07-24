<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class stp_state extends Model
{
    use HasFactory;

    public function studentDetail(): HasMany
    {
        return $this->hasMany(stp_student_detail::class, 'state_id', 'id');
    }

    public function school(): HasMany
    {
        return $this->hasMany(stp_school::class, 'state_id', 'id');
    }
}
