<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_package extends Model
{
    use HasFactory;

    protected $fillable = ([
        'package_name',
        'package_detail',
        'package_type',
        'package_price',
        'package_status',
        'updated_by',
        'created_by'
    ]);

    public function type(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'package_type', 'id');
    }
}
