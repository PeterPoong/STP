<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class stp_courses_category extends Model
{
    use HasFactory;

    protected $fillable = [
        "category_name",
        "category_description",
        "category_icon",
        "course_hotPick",
        "category_status",
        "updated_by",
        "created_by"
    ];

    public function courses(): HasMany
    {
        return $this->hasMany(stp_course::class, 'category_id', 'id');
    }
}