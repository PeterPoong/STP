<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_user_media extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }


    public function type()
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'userMedia_type');
    }
}
