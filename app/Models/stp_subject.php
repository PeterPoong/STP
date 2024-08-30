<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class stp_subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_name',
        'subject_category',
        'subject_status',
        'updated_by',
        'created_by'
    ];

    public function transcripts(): HasMany
    {
        return $this->hasMany(stp_transcript::class, 'subject_id', 'id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'subject_category', 'id');
    }

}
