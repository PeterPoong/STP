<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'country_code',
        'contact_no',
        'user_role',
        'profile_pic',
        'created_by',
        'updated_by'
    ];

    protected $table = 'stp_users';
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(stp_core_meta::class, 'user_role', 'id');
    }


    // public function detail()
    // {
    //     return $this->hasOne(stp_user_detail::class);
    // }


    public function media()
    {
        return $this->belongsToMany(stp_user_media::class);
    }

    public function transcript()
    {
        return $this->belongsToMany(stp_transcript::class);
    }

    public function detail(): HasOne
    {
        return $this->hasOne(stp_user_detail::class, 'user_id', 'id');
    }

    public function otp(): HasMany
    {
        return $this->hasMany(stp_user_otp::class, 'user_id', 'id');
    }
}
