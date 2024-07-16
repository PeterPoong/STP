<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_course extends Model
{
    use HasFactory;

    public function school()
    {
        return $this->hasOne(stp_school::class, 'id', 'school_id');
    }

    public function category()
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'course_category');
    }

    public  function tag()
    {
        return $this->belongsToMany(stp_course_tag::class);
    }

    public function media()
    {
        return $this->belongsToMany(stp_course_media::class);
    }

    public function qualification()
    {
        return $this->hasOne(stp_qualification::class, 'id', 'course_qualification');
    }

    public function featured()
    {
        return $this->belongsToMany(stp_featured::class);
    }
}
