<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_totalNumberVisit extends Model
{
    use HasFactory;
    protected $fillable = ([
        'school_id',
        'totalNumberVisit',
        'status',
        'updated_by',
        'created_by'
    ]);

    public function school()
    {
        return $this->hasOne(stp_school::class, 'id', 'school_id');
    }
}
