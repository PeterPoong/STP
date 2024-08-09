<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'achievement_name',
        'title_obtained',
        'achievement_media',
        'date',
        'achivements_status',
        'updated_by',
        'created_by'
    ];

    public function student():BelongsTo
    {
        return $this->belongTo(stp_student::class, 'student_id', 'id');

    }

    public function title():BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'title_obtained', 'id');

    }
}
