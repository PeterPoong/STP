<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_personalityQuestions extends Model
{
    use HasFactory;
    protected $fillable = [
        'question',
        'riasec_type',
        'status'
    ];

    public function question_type(): BelongsTo
    {
        return $this->belongsTo(stp_RIASECType::class, 'riasec_type', 'id');
    }
}
