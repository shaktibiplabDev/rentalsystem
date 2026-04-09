<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rental extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'vehicle_id',
        'customer_id',
        'phase',
        'status',
        'start_time',
        'end_time',
        'total_price',
        'verification_fee_deducted',
        'verification_transaction_id',
        'is_verification_cached',
        'verification_reference_id',
        'verification_completed_at',
        'document_id',
        'document_upload_completed_at',
        'agreement_path',
        'signed_agreement_path',
        'customer_with_vehicle_image',
        'vehicle_condition_video',
        'agreement_signed_at',
        'vehicle_in_good_condition',
        'damage_amount',
        'damage_description',
        'damage_images',
        'return_completed_at',
        'receipt_path',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'total_price' => 'decimal:2',
    ];

    /**
     * Get the user that owns the rental.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the vehicle associated with the rental.
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the customer associated with the rental.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the document associated with the rental.
     */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
