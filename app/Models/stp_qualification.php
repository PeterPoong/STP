<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_qualification extends Model
{
    use HasFactory;

    public function courses()
    {
        return $this->belongsToMany(stp_course::class);
    }
}
