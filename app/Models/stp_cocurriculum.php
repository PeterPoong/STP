<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_cocurriculum extends Model
{
    protected $fillable = [

        'student_id',
        'club_name',
        'student_position',
        'year',
        'cocurriculums_status',
        'updated_by',
        'created_by',
    ];
    use HasFactory;

    public function student():BelongsTo
    {
        return $this->belongTo(stp_student::class, 'student_id', 'id');

    }
}
