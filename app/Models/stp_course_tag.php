<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_course_tag extends Model
{
    use HasFactory;

    public function courses()
    {
        return $this->hasMany(stp_course::class, 'id', 'course_id');
    }

    public function tag()
    {
        return $this->hasMany(stp_tag::class, 'id', 'tag_id');
    }
}