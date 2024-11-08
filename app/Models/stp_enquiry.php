<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class stp_enquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        "enquiry_name",
        "enquiry_email",
        "enquiry_phone",
        "enquiry_subject",
        "enquiry_message",
        "enquiry_status",
        "updated_by",
        "created_by"
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'enquiry_subject', 'id');
    }
}
