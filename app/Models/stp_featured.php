<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class stp_featured extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'school_id',
        'featured_startTime',
        'featured_endTime',
        'featured_type',
        'featured_status',
        'updated_by',
        'created_by'
    ];

    public function courses(): BelongsTo
    {
        return $this->belongsTo(stp_course::class, 'course_id', 'id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(stp_school::class, 'school_id', 'id');
    }
    public function featured(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'featured_type', 'id');
    }
}
