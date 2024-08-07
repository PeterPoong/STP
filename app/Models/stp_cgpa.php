<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_cgpa extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'transcript_category',
        'cgpa',
        'cgpa_status',
        'created_by',
        'updated_by'
    ];

    public function student()
    {
        return $this->belongTo(stp_student::class, 'student_id', 'id');

    }

    public function category()
    {
        return $this->belongsTo(stp_core_meta::class, 'transcript_category', 'id');

    }

}
