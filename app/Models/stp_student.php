<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;



class stp_student extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'student_userName',
        'student_email',
        'student_password',
        'student_icNumber',
        'student_countryCode',
        'student_contactNo',
        'user_role',
        'student_profilePic',
        'student_status',
        'facebook_id',
        'created_by',
        'updated_by',
        'google_id',
        'terms_agreed',
        'terms_agreed_at',
        'updated_by'
    ];

    public function role()
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'user_role');
    }

    public function media()
    {
        return $this->belongsToMany(stp_user_media::class);
    }

    public function detail(): HasOne
    {
        return $this->hasOne(stp_student_detail::class, 'student_id', 'id');
    }

    public function higherTranscript(): HasMany
    {
        return $this->hasMany(stp_higher_transcript::class, 'student_id', 'id');
    }
    public function transcript(): HasMany
    {
        return $this->hasMany(stp_transcript::class, 'student_id', 'id');
    }
    public function cgpa(): HasMany
    {
        return $this->hasMany(stp_cgpa::class, 'student_id', 'id');
    }

    public function cocurriculum(): HasMany
    {
        return $this->hasMany(stp_cocurriculum::class, 'student_id', 'id');
    }

    public function achievement(): HasMany
    {
        return $this->hasMany(stp_core_meta::class, 'student_id', 'id');
    }

    public function award(): HasMany
    {
        return $this->hasMany(stp_achievement::class, 'student_id', 'id');
    }

    public function studentMedia(): HasMany
    {
        return $this->hasMany(stp_student_media::class, 'student_id', 'id');
    }
    public function otp(): HasMany
    {
        return $this->hasMany(stp_student_otp::class, 'student_id', 'id');
    }

    public function otherCertificate(): HasMany
    {
        return $this->hasMany(stp_other_certificate::class, 'student_id', 'id');
    }
}
