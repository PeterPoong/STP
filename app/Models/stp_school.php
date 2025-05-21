<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'country_id',
        'state_id',
        'city_id',
        'institue_category',
        'school_location',
        'school_lg',
        'school_lat',
        'school_status',
        'school_officalWebsite',
        'school_google_map_location',
        'person_inChargeName',
        'person_inChargeNumber',
        'person_inChargeEmail',
        'account_type',
        'school_logo',
        'created_by',
        'updated_by'
    ];

    public function socialMedia()
    {
        return $this->belongsToMany(stp_school_media::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(stp_course::class, 'school_id', 'id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(stp_school_media::class, 'school_id', 'id');
    }

    public function featured(): HasMany
    {
        return $this->hasMany(stp_featured::class, 'school_id', 'id');
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

    public function otp(): HasMany
    {
        return $this->hasMany(stp_school_otp::class, 'school_id', 'id');
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'account_type', 'id');
    }

    public function requestFeatured(): HasMany
    {
        return $this->hasMany(stp_featured_request::class, 'school_id', 'id');
    }

    public function numberVisit(): HasMany
    {
        return $this->hasMany(stp_totalNumberVisit::class, 'school_id', 'id');
    }
}
