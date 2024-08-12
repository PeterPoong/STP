<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function country()
    {
        return $this->hasOne(stp_country::class, 'id', 'country_id');
    }

    public function transcript()
    {
        return $this->belongsToMany(stp_transcript::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'subject_category', 'id');
    }
}
