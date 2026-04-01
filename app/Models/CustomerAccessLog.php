<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAccessLog extends Model
{
    public $timestamps = false; // Using created_at only
    
    protected $fillable = [
        'customer_id',
        'user_id',
        'action',
        'rental_id',
        'metadata',
        'created_at'
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime'
    ];
    
    /**
     * Get the customer that was accessed
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    
    /**
     * Get the user (shop owner) who accessed
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the rental associated with this access (if any)
     */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
    
    /**
     * Scope a query to only include logs for a specific customer
     */
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
    
    /**
     * Scope a query to only include logs for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
    
    /**
     * Scope a query to only include logs for a specific action
     */
    public function scopeWithAction($query, $action)
    {
        return $query->where('action', $action);
    }
    
    /**
     * Scope a query to only include logs from a date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}