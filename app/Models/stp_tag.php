<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class stp_tag extends Model
{
    use HasFactory;
    protected $fillable = [
        'tag_name'
    ];

    public function coursesTag(): HasMany
    {
        return $this->hasMany(stp_tag::class, 'tag_id', 'id');
    }
}
