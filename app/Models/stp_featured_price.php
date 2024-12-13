<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_featured_price extends Model
{
    use HasFactory;

    protected $table = 'stp_featured_price'; // Explicitly define the table name

    protected $fillable = [
        'featured',
        'featured_price',
        'created_by',
        'updated_by',
    ];

    public function featured_name(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'featured_id', 'id');
    }
}
