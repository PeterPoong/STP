<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_course_media extends Model
{
    use HasFactory;

    public function courses()
    {
        return $this->hasOne(stp_course::class, 'id', 'course_id');
    }

    public function type()
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'courseMedia_type');
    }
}
