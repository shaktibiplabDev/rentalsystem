<?php
// app/Models/EmailVerification.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerification extends Model
{
    protected $table = 'email_verifications';
    
    protected $fillable = [
        'user_id',
        'token',
        'otp',
        'expires_at',
        'is_used'
    ];
    
    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}