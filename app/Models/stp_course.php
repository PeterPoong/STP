<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class stp_course extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'course_name',
        'course_description',
        'course_requirement',
        'course_cost',
        'course_period',
        'course_intake',
        'category_id',
        'qualification_id',
        'course_logo',
        'course_status',
        'updated_by',
        'created_by'
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(stp_school::class, 'school_id', 'id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(stp_courses_category::class, 'category_id', 'id');
    }

    public  function tag()
    {
        return $this->belongsToMany(stp_course_tag::class);
    }

    public function media()
    {
        return $this->belongsToMany(stp_course_media::class);
    }

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(stp_qualification::class, 'qualification_id', 'id');
    }

    public function featured(): HasMany
    {
        return $this->hasMany(stp_featured::class, 'course_id', 'id');
    }
}