<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Customer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'father_name',
        'phone',
        'address',
        'license_address',
        'license_address_list',
        'license_number',
        'license_number_hash',
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

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'license_address_list' => 'array',
        'license_data' => 'array',
        'aadhaar_data' => 'array',
        'vehicle_classes_data' => 'array',
        'date_of_birth' => 'date',
        'license_issue_date' => 'date',
        'license_valid_from_non_transport' => 'date',
        'license_valid_to_non_transport' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // Sensitive fields that should never be exposed in API responses
        'aadhaar_number_raw',
        'license_number_raw',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'masked_aadhaar',
        'masked_license',
        'license_is_valid',
        'formatted_date_of_birth',
        'formatted_license_valid_from',
        'formatted_license_valid_to',
    ];

    // =============================================
    // ENCRYPTION METHODS FOR SENSITIVE DATA
    // =============================================

    /**
     * Encrypt Aadhaar number before saving to database
     */
    public function setAadhaarNumberAttribute($value)
    {
        if ($value) {
            try {
                // Remove any non-numeric characters
                $value = preg_replace('/[^0-9]/', '', $value);
                $this->attributes['aadhaar_number'] = Crypt::encryptString($value);
            } catch (\Exception $e) {
                Log::error('Failed to encrypt Aadhaar number', [
                    'customer_id' => $this->id ?? null,
                    'error' => $e->getMessage()
                ]);
                $this->attributes['aadhaar_number'] = null;
            }
        } else {
            $this->attributes['aadhaar_number'] = null;
        }
    }

    /**
     * Decrypt Aadhaar number when retrieving from database
     */
    public function getAadhaarNumberAttribute($value)
    {
        if ($value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                Log::error('Failed to decrypt Aadhaar number', [
                    'customer_id' => $this->id ?? null,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        }
        return null;
    }

    /**
     * Get raw encrypted Aadhaar (for internal use)
     */
    public function getAadhaarNumberRawAttribute()
    {
        return $this->attributes['aadhaar_number'] ?? null;
    }

    /**
     * Encrypt License number before saving to database
     */
    public function setLicenseNumberAttribute($value)
    {
        if ($value) {
            try {
                $this->attributes['license_number'] = Crypt::encryptString(strtoupper($value));
            } catch (\Exception $e) {
                Log::error('Failed to encrypt License number', [
                    'customer_id' => $this->id ?? null,
                    'error' => $e->getMessage()
                ]);
                $this->attributes['license_number'] = null;
            }
        } else {
            $this->attributes['license_number'] = null;
        }
    }

    /**
     * Decrypt License number when retrieving from database
     */
    public function getLicenseNumberAttribute($value)
    {
        if ($value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                Log::error('Failed to decrypt License number', [
                    'customer_id' => $this->id ?? null,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        }
        return null;
    }

    /**
     * Get raw encrypted License (for internal use)
     */
    public function getLicenseNumberRawAttribute()
    {
        return $this->attributes['license_number'] ?? null;
    }

    // =============================================
    // MASKING METHODS FOR DISPLAY
    // =============================================

    /**
     * Get masked Aadhaar number for display (XXXX-XXXX-1234)
     */
    public function getMaskedAadhaarAttribute(): ?string
    {
        $aadhaar = $this->aadhaar_number;
        if (!$aadhaar) {
            return null;
        }
        
        // Remove any non-numeric characters
        $aadhaar = preg_replace('/[^0-9]/', '', $aadhaar);
        
        if (strlen($aadhaar) === 12) {
            return 'XXXX-XXXX-' . substr($aadhaar, -4);
        } elseif (strlen($aadhaar) > 4) {
            return str_repeat('X', strlen($aadhaar) - 4) . substr($aadhaar, -4);
        }
        
        return 'XXXX';
    }

    /**
     * Get masked License number for display (DL01****4567)
     */
    public function getMaskedLicenseAttribute(): ?string
    {
        $license = $this->license_number;
        if (!$license) {
            return null;
        }
        
        $license = strtoupper($license);
        
        if (strlen($license) <= 8) {
            return substr($license, 0, 2) . str_repeat('*', strlen($license) - 2);
        }
        
        return substr($license, 0, 4) . str_repeat('*', strlen($license) - 8) . substr($license, -4);
    }

    /**
     * Check if license is currently valid
     */
    public function getLicenseIsValidAttribute(): bool
    {
        if (!$this->license_valid_to_non_transport) {
            return false;
        }
        
        return Carbon::now()->lessThanOrEqualTo($this->license_valid_to_non_transport);
    }

    /**
     * Get formatted date of birth
     */
    public function getFormattedDateOfBirthAttribute(): ?string
    {
        if (!$this->date_of_birth) {
            return null;
        }
        
        return $this->date_of_birth->format('d M Y');
    }

    /**
     * Get formatted license valid from date
     */
    public function getFormattedLicenseValidFromAttribute(): ?string
    {
        if (!$this->license_valid_from_non_transport) {
            return null;
        }
        
        return $this->license_valid_from_non_transport->format('d M Y');
    }

    /**
     * Get formatted license valid to date
     */
    public function getFormattedLicenseValidToAttribute(): ?string
    {
        if (!$this->license_valid_to_non_transport) {
            return null;
        }
        
        return $this->license_valid_to_non_transport->format('d M Y');
    }

    /**
     * Get days until license expiry
     */
    public function getDaysUntilLicenseExpiryAttribute(): ?int
    {
        if (!$this->license_valid_to_non_transport) {
            return null;
        }
        
        $now = Carbon::now();
        $expiry = $this->license_valid_to_non_transport;
        
        if ($now->greaterThan($expiry)) {
            return -$now->diffInDays($expiry); // Negative if expired
        }
        
        return $now->diffInDays($expiry);
    }

    /**
     * Check if license is expiring soon (within 30 days)
     */
    public function getLicenseExpiringSoonAttribute(): bool
    {
        $daysUntilExpiry = $this->days_until_license_expiry;
        
        if ($daysUntilExpiry === null) {
            return false;
        }
        
        return $daysUntilExpiry >= 0 && $daysUntilExpiry <= 30;
    }

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * Get the user (shop owner) that owns the customer.
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

    /**
     * Get the documents for the customer (through rentals).
     */
    public function documents()
    {
        return $this->hasManyThrough(Document::class, Rental::class);
    }

    /**
     * Get the access logs for the customer.
     */
    public function accessLogs()
    {
        return $this->hasMany(CustomerAccessLog::class);
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope a query to only include verified customers (with license data).
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('license_data');
    }

    /**
     * Scope a query to only include unverified customers.
     */
    public function scopeUnverified($query)
    {
        return $query->whereNull('license_data');
    }

    /**
     * Scope a query to only include customers with Aadhaar.
     */
    public function scopeWithAadhaar($query)
    {
        return $query->whereNotNull('aadhaar_number');
    }

    /**
     * Scope a query to only include customers without Aadhaar.
     */
    public function scopeWithoutAadhaar($query)
    {
        return $query->whereNull('aadhaar_number');
    }

    /**
     * Scope a query to only include customers with valid license.
     */
    public function scopeValidLicense($query)
    {
        return $query->whereNotNull('license_valid_to_non_transport')
            ->where('license_valid_to_non_transport', '>=', Carbon::now());
    }

    /**
     * Scope a query to only include customers with expired license.
     */
    public function scopeExpiredLicense($query)
    {
        return $query->whereNotNull('license_valid_to_non_transport')
            ->where('license_valid_to_non_transport', '<', Carbon::now());
    }

    /**
     * Scope a query to only include customers with license expiring soon.
     */
    public function scopeLicenseExpiringSoon($query, int $days = 30)
    {
        $expiryDate = Carbon::now()->addDays($days);
        
        return $query->whereNotNull('license_valid_to_non_transport')
            ->where('license_valid_to_non_transport', '<=', $expiryDate)
            ->where('license_valid_to_non_transport', '>=', Carbon::now());
    }

    /**
     * Scope a query to search customers.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('phone', 'LIKE', "%{$search}%");
        });
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    /**
     * Get total rental count for this customer.
     */
    public function getTotalRentalsCount(): int
    {
        return $this->rentals()->count();
    }

    /**
     * Get total amount spent by this customer.
     */
    public function getTotalSpent(): float
    {
        return (float) $this->rentals()
            ->where('status', 'completed')
            ->sum('total_price');
    }

    /**
     * Get active rentals for this customer.
     */
    public function getActiveRentals()
    {
        return $this->rentals()
            ->where('status', 'active')
            ->with(['vehicle', 'user'])
            ->get();
    }

    /**
     * Get completed rentals for this customer.
     */
    public function getCompletedRentals()
    {
        return $this->rentals()
            ->where('status', 'completed')
            ->with(['vehicle', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get the most rented vehicle by this customer.
     */
    public function getFavoriteVehicle()
    {
        return $this->rentals()
            ->select('vehicle_id', \DB::raw('COUNT(*) as rental_count'))
            ->where('status', 'completed')
            ->groupBy('vehicle_id')
            ->orderBy('rental_count', 'desc')
            ->with('vehicle')
            ->first();
    }

    /**
     * Get last rental date.
     */
    public function getLastRentalDate(): ?Carbon
    {
        $lastRental = $this->rentals()
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->first();
            
        return $lastRental ? $lastRental->created_at : null;
    }

    /**
     * Get first rental date.
     */
    public function getFirstRentalDate(): ?Carbon
    {
        $firstRental = $this->rentals()
            ->where('status', 'completed')
            ->orderBy('created_at', 'asc')
            ->first();
            
        return $firstRental ? $firstRental->created_at : null;
    }

    /**
     * Check if customer has completed any rentals.
     */
    public function hasRentalHistory(): bool
    {
        return $this->rentals()->where('status', 'completed')->exists();
    }

    /**
     * Get customer statistics as array.
     */
    public function getStatistics(): array
    {
        return [
            'total_rentals' => $this->getTotalRentalsCount(),
            'total_spent' => $this->getTotalSpent(),
            'formatted_total_spent' => '₹' . number_format($this->getTotalSpent(), 2),
            'active_rentals' => $this->rentals()->where('status', 'active')->count(),
            'completed_rentals' => $this->rentals()->where('status', 'completed')->count(),
            'cancelled_rentals' => $this->rentals()->where('status', 'cancelled')->count(),
            'last_rental_date' => $this->getLastRentalDate(),
            'first_rental_date' => $this->getFirstRentalDate(),
            'favorite_vehicle' => $this->getFavoriteVehicle(),
        ];
    }

    /**
     * Check if Aadhaar is provided.
     */
    public function hasAadhaar(): bool
    {
        return !is_null($this->aadhaar_number);
    }

    /**
     * Check if license is verified.
     */
    public function isLicenseVerified(): bool
    {
        return !is_null($this->license_data);
    }

    /**
     * Get license photo URL.
     */
    public function getLicensePhotoUrlAttribute(): ?string
    {
        if ($this->license_photo && \Storage::disk('public')->exists($this->license_photo)) {
            return url('/media/' . ltrim($this->license_photo, '/'));
        }
        
        return null;
    }

    /**
     * Get vehicle classes as array.
     */
    public function getVehicleClasses(): array
    {
        return $this->vehicle_classes_data ?? [];
    }

    /**
     * Get license address list as array.
     */
    public function getLicenseAddressList(): array
    {
        return $this->license_address_list ?? [];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-sanitize phone number before saving
        static::saving(function ($customer) {
            if ($customer->phone) {
                $customer->phone = preg_replace('/[^0-9]/', '', $customer->phone);
            }
        });
    }
}
