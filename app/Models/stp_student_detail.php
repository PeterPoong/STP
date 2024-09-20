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
        'country_id',
        'gender',
        'city_id',
        'student_contactNo',
        'state_id',
        'student_detailPostcode',
        'student_detailStatus',
        'created_by',
        'updated_by'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(stp_student::class, 'student_id', 'id');
    }



    public function country(): BelongsTo
    {
        return $this->belongsTo(stp_country::class, 'country_id', 'id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(stp_city::class, 'city_id', 'id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(stp_state::class, 'state_id', 'id');
    }

    public function studentGender(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'gender', 'id');
    }
}
