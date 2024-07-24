<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class stp_core_meta extends Model
{
    use HasFactory;

    public function studentRole()
    {
        return $this->belongsToMany(stp_student::class);
    }

    public function users(): HasMany
    {
        // return $this->belongsToMany(User::class);

        // return $this->belongsToMany(User::class);
        // return $this->belongsToMany(User::class);
        return $this->hasMany(User::class, 'user_role', 'id');
    }

    public function uniStaff()
    {
        return $this->belongsToMany(stp_uni_staff::class);
    }

    public function socialMediaType()
    {
        return $this->belongsToMany(stp_school_social_media::class);
    }


    public function courses()
    {
        return $this->belongsToMany(stp_course::class);
    }

    public function schoolMedia()
    {
        return $this->belongsToMany(stp_school_media::class);
    }

    public function userMedia()
    {
        return $this->belongsToMany(stp_user_media::class);
    }

    public function studentMedia()
    {
        return $this->belongsToMany(stp_student_media::class);
    }

    public function coursesMedia()
    {
        return $this->belongsToMany(stp_course_media::class);
    }

    public function transcript()
    {
        return $this->belongsToMany(stp_transcript::class);
    }

    public function schoolInstitueCategory(): HasMany
    {
        return $this->hasMany(stp_school::class, 'institue_category', 'id');
    }
}
