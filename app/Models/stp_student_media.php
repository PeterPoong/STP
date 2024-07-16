<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class stp_student_media extends Model
{
    use HasFactory;

    public function student()
    {
        return $this->hasOne(stp_student::class, 'id', 'student_id');
    }

    public function type()
    {
        return $this->hasOne(stp_core_meta::class, 'id', 'studentMedia_type');
    }
}
