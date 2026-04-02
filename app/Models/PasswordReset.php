<?php
// app/Models/PasswordReset.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    protected $table = 'password_resets';
    
    protected $fillable = [
        'email',
        'token',
        'otp',
        'expires_at',
        'is_used'
    ];
    
    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean'
    ];
}