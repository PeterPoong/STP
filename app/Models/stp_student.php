<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;



class stp_student extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'student_userName',
        'student_email',
        'student_password',
        'student_icNumber',
        'student_countryCode',
        'student_contactNo',
        'user_role',
        'student_proilePic',
        'student_status',
        'created_by',
        'updated_by'
    ];

    public function role()
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'user_role');
    }

    public function media()
    {
        return $this->belongsToMany(stp_user_media::class);
    }

    public function detail(): HasOne
    {
        return $this->hasOne(stp_student_detail::class, 'student_id', 'id');
    }
}
