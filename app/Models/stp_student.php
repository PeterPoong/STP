<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_student extends Model
{
    use HasFactory;

    public function role()
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'user_role');
    }

    public function media()
    {
        return $this->belongsToMany(stp_user_media::class);
    }
}
