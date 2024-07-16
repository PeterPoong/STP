<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_school_media extends Model
{
    use HasFactory;

    public function school()
    {
        return $this->hasOne(stp_school::class, 'id', 'school_id');
    }

    public function type()
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'schoolMedia_type');
    }
}
