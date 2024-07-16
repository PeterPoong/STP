<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_featured extends Model
{
    use HasFactory;

    public function courses()
    {
        return $this->hasMany(stp_course::class, 'id', 'course_id');
    }

    public function school()
    {
        return $this->hasMany(stp_school::class, 'id', 'school_id');
    }
}
