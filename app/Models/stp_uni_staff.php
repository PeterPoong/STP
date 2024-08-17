<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_uni_staff extends Model
{
    use HasFactory;
    protected $fillable = [
        'uniStaff_userName',
        'uniStaff_email',
        'uniStaff_password',
        'uniStaff_icNumber',
        'uniStaff_countryCode',
        'uniStaff_contactNo',
        'user_role',
        'uniStaff_profilePic',
        'uniStaff_status',
        'created_by',
        'updated_by'
    ];

    public  function role()
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'user_role');
    }
}
