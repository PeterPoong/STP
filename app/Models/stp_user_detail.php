<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_user_detail extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_detailFirstName',
        'user_detailLastName',
        'user_detailAddress',
        'country_id',
        'city_id',
        'user_contactNo',
        'state_id',
        'user_detailPostcode',
        'user_detailStatus',
        'created_by',
        'updated_by'
    ];

    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(stp_country::class, 'country_id', 'id');
    }
}
