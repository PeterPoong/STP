<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class stp_student_media extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'studentMedia_name',
        'studentMedia_location',
        'studentMedia_type',
        'studentMedia_format',
        'studentMedia_status',
        'updated_by',
        'created_by'
    ];

    public function student() :BelongsTo
    {
        return $this->belongsTo(stp_student::class, 'student_id', 'id');
    }

    public function type() :BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'studentMedia_type', 'id');
    }
}
