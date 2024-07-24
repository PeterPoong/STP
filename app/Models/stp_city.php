<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class stp_city extends Model
{
    use HasFactory;

    public function studentDetail(): HasMany
    {
        return $this->hasMany(stp_student_detail::class, 'city_id', 'id');
    }

    public function school(): HasMany
    {
        return $this->hasMany(stp_school::class, 'city_id', 'id');
    }
}
