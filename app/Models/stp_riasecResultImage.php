<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_riasecResultImage extends Model
{
    use HasFactory;
    protected $fillable = [
        'resultImage_location',
        'riasec_imageType',
        'student_id',
        'status'
    ];
    public function imageType(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'riasec_imageType', 'id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(stp_student::class, 'student_id', 'id');
    }
}
