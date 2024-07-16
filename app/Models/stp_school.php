<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_school extends Model
{
    use HasFactory;

    public function socialMedia()
    {
        return $this->belongsToMany(stp_school_media::class);
    }

    public function courses()
    {
        return $this->belongsToMany(stp_course::class);
    }

    public function media()
    {
        return $this->belongsToMany(stp_school_media::class);
    }

    public function featured()
    {
        return $this->belongsToMany(stp_featured::class);
    }
}
