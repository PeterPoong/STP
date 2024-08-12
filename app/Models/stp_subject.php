<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class stp_subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_name',
        'subject_category',
        'subject_status',
        'updated_by',
        'created_by'
    ];

   

    public function category()
    {
        return $this->hasOne(stp_core_meta::class, 'id ', 'subject_category');
    }

    public function transcripts():HasMany
    {
        return $this->hasMany(stp_transcript::class, 'subject_id', 'id');
    }
}
