<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class stp_RIASECType extends Model
{
    use HasFactory;
    protected $table = 'stp_riasecTypes';

    protected $fillable = [
        'type_name',
        'unique_description',
        'strength',
        'status'
    ];

    public function personalityQuestion(): hasMany
    {
        return $this->hasMany(stp_personalityQuestions::class, 'riasec_type', 'id');
    }
}
