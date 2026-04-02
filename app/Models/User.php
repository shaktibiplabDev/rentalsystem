<?php
// app/Models/User.php (updated version)
namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'role',
        'wallet_balance',
        'email_verified_at'
    ];

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
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'wallet_balance' => 'decimal:2',
        'password' => 'hashed',
    ];

    /**
     * Get all vehicles owned by the user.
     */
    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Get all rentals associated with the user.
     */
    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Get all wallet transactions for the user.
     */
    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Get all user settings.
     */
    public function settings()
    {
        return $this->hasMany(UserSetting::class);
    }

    /**
     * Get all notifications for the user.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
    
    /**
     * Get email verification record.
     */
    public function emailVerification()
    {
        return $this->hasOne(EmailVerification::class);
    }
    
    /**
     * Check if email is verified.
     */
    public function hasVerifiedEmail()
    {
        return !is_null($this->email_verified_at);
    }
    
    /**
     * Mark email as verified.
     */
    public function markEmailAsVerified()
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }
}