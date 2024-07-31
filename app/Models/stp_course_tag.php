<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_course_tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'tag_id',
        'courseTag_status'
    ];

    public function courses(): BelongsTo
    {
        return $this->belongsTo(stp_course::class, 'course_id', 'id');
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(stp_tag::class, 'tag_id', 'id');
    }
}
