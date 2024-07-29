<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class stp_qualification extends Model
{
    use HasFactory;

    public function courses(): HasMany
    {
        return $this->hasMany(stp_course::class, 'qualification_id', 'id');
    }
}
