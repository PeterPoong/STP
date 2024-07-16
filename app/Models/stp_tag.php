<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_tag extends Model
{
    use HasFactory;

    public function coursesTag()
    {
        return $this->belongsToMany(stp_course_tag::class);
    }
}
