<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class stp_country extends Model
{
    use HasFactory;

    public function subject()
    {
        return $this->belongsToMany(stp_subject::class);
    }

    public function studentDetail(): HasMany
    {
        return $this->hasMany(stp_student_detail::class, 'country_id', 'id');
    }

    public function school(): HasMany
    {
        return $this->hasMany(stp_school::class, 'country_id', 'id');
    }
}
