<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_advertisement_banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'banner_name',
        'banner_file',
        'banner_url',
        'featured_id',
        'banner_start',
        'banner_end',
        'banner_status',
        'created_by',
        'updated_by'
    ];

// In stp_advertisement_banner.php
        public function banner(): BelongsTo
        {
            return $this->belongsTo(stp_core_meta::class, 'featured_id', 'id');
        }

}
