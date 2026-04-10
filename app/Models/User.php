<?php

// app/Models/User.php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'role',
        'wallet_balance',
        'email_verified_at',
        'google_id',
        'avatar',
        'is_google_user',
        'google_verified_at',
        'password_set_required',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'google_verified_at' => 'datetime',
        'wallet_balance' => 'decimal:2',
        'password' => 'hashed',
        'is_google_user' => 'boolean',
        'password_set_required' => 'boolean',
    ];

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function settings()
    {
        return $this->hasMany(UserSetting::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function emailVerification()
    {
        return $this->hasOne(EmailVerification::class);
    }

    public function hasVerifiedEmail()
    {
        return ! is_null($this->email_verified_at);
    }

    public function markEmailAsVerified()
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    /**
     * Get user's avatar URL
     */
    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return $this->avatar;
        }

        // Generate default avatar
        return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&background=4F46E5&color=fff';
    }

    /**
     * Check if user can login with password
     */
    public function hasPassword()
    {
        return ! is_null($this->password);
    }

    /**
     * Check if user needs to set password
     */
    public function needsPasswordSetup()
    {
        return $this->is_google_user && ! $this->hasPassword() && $this->password_set_required;
    }

    protected static function booted()
    {
        static::updated(function ($user) {
            if ($user->wasChanged('wallet_balance')) {
                Cache::forget('wallet_balance_'.$user->id);
            }
        });
    }
}
