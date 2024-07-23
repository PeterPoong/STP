<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class stp_student_detail extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'student_detailFirstName',
        'student_detailLastName',
        'student_detailAddress',
        'student_detailCountry',
        'student_detailCity',
        'student_contactNo',
        'student_detailState',
        'student_detailPostcode',
        'student_detailStatus',
        'created_by',
        'updated_by'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(stp_student::class, 'student_id', 'id');
    }
}
