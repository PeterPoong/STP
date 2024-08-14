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

    public function studentRole(): BelongsToMany
    {
        return $this->belongsToMany(stp_student::class, 'student_role_pivot_table', 'core_meta_id', 'student_id');
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
        return $this->belongsToMany(stp_transcript::class, 'transcript_category', 'id');
    }

    public function schoolInstitueCategory(): HasMany
    {
        return $this->hasMany(stp_school::class, 'institue_category', 'id');
    }

    public function courseStudyMode(): HasMany
    {
        return $this->hasMany(stp_course::class, 'study_mode', 'id');
    }

    public function higherTranscriptCategory(): HasMany
    {
        return $this->hasMany(stp_higher_transcript::class, 'category_id', 'id');
    }

    public function higherTranscriptGrade(): HasMany
    {
        return $this->hasMany(stp_higher_transcript::class, 'higherTranscript_grade', 'id');
    }

    public function transcriptGrade(): HasMany
    {
        return $this->hasMany(stp_transcript::class, 'transcript_grade', 'id');
    }


    public function cgpa(): HasMany
    {
        return $this->hasMany(stp_cgpa::class, 'transcript_category', 'id');
    }

    public function achievement(): HasMany
    {
        return $this->hasMany(stp_achievement::class, 'title_obtained', 'id');
    }

    public function studentGender(): HasMany
    {
        return $this->hasMany(stp_student_detail::class, 'gender', 'id');
    }

    public function subjectCategory(): HasMany
    {
        return $this->hasMany(stp_subject::class, 'subject_category', 'id');
    }

    public function packageType(): HasMany
    {
        return $this->hasMany(stp_package::class, 'package_type', 'id');
    }
}
