<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_country extends Model
{
    use HasFactory;

    public function subject()
    {
        return $this->belongsToMany(stp_subject::class);
    }
}
