<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class stp_featured_request extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'request_name',
        'featured_type',
        'request_quantity',
        'request_type',
        'featured_duration',
        'featured_startTime',
        'request_featured_duration',
        'request_transaction_prove',
        'featured_endTime',
        'request_status'
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(stp_school::class, 'school_id', 'id');
    }

    public function featured(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'featured_type', 'id');
    }

    public function featuredCourse(): HasMany
    {
        return $this->hasMany(stp_featured::class, 'request_id', 'id');
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'request_type', 'id');
    }
}
