<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_school_social_media extends Model
{
    use HasFactory;

    public function type()
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'schoolSocialMedia_type');
    }

    public function school()
    {
        return $this->hasOne(stp_school::class, 'id', 'school_id');
    }
}
