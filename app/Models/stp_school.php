<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;


class stp_school extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'school_name',
        'school_email',
        'school_password',
        'school_countryCode',
        'school_contactNo',
        'school_fullDesc',
        'school_shortDesc',
        'school_address',
        'school_lg',
        'school_lat',
        'school_officalWebsite',
        'school_logo',
        'created_by',
        'updated_by'
    ];

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
