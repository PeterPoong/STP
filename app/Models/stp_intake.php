<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_intake extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'intake_month',
        'intake_status',
        'updated_by',
        'created_by'
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(stp_course::class, 'course_id', 'id');
    }

    public function month(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'intake_month', 'id');
    }
}
