<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class stp_submited_form extends Model
{
    use HasFactory;
    protected $fillable = [
        'student_id',
        'courses_id',
        'form_status',
        'updated_by',
        'created_by'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(stp_student::class, 'student_id', 'id');
    }

    public  function course(): BelongsTo
    {
        return $this->belongsTo(stp_course::class, 'courses_id', 'id');
    }
    
}

