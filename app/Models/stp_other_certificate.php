<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_other_certificate extends Model
{
    protected $fillable = [

        'student_id',
        'certificate_name',
        'certificate_media',
        'certificate_status',
        'updated_by',
        'created_by',
    ];
    use HasFactory;

    public function student(): BelongsTo
    {
        return $this->belongsTo(stp_student::class, 'student_id', 'id');
    }
}
