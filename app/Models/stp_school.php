<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'institue_category',
        'country_id',
        'state_id',
        'city_id',
        'institue_category',
        'school_lat',
        'school_status',
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

    public function country(): BelongsTo
    {
        return $this->belongsTo(stp_country::class, 'country_id', 'id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(stp_state::class, 'state_id', 'id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(stp_city::class, 'city_id', 'id');
    }

    public function institueCategory(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'institue_category', 'id');
    }
}
