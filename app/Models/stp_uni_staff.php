<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_uni_staff extends Model
{
    use HasFactory;


    public  function role()
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'user_role');
    }
}
