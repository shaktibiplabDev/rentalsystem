<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // app/Models/Customer.php

    protected $fillable = [
        'user_id',
        'name',
        'father_name',
        'phone',
        'address',
        'license_address',
        'license_address_list',
        'license_number',
        'aadhaar_number',
        'date_of_birth',
        'license_issue_date',
        'license_valid_from_non_transport',
        'license_valid_to_non_transport',
        'license_photo',
        'license_data',
        'aadhaar_data',
        'license_reference_id',
        'vehicle_classes_data'
    ];

    protected $casts = [
        'license_address_list' => 'array',
        'license_data' => 'array',
        'aadhaar_data' => 'array',
        'vehicle_classes_data' => 'array',
        'date_of_birth' => 'date',
        'license_issue_date' => 'date',
        'license_valid_from_non_transport' => 'date',
        'license_valid_to_non_transport' => 'date',
    ];
    /**
     * Get the user that owns the customer.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the rentals for the customer.
     */
    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }
}
