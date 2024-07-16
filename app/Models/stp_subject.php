<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_subject extends Model
{
    use HasFactory;

    public function country()
    {
        return $this->hasOne(stp_country::class, 'id', 'country_id');
    }

    public function transcript()
    {
        return $this->belongsToMany(stp_transcript::class);
    }
}
