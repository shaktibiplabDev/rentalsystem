<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rental_id',
        'aadhaar_image',
        'license_image',
        'is_verified',
        'verification_status',
        'aadhaar_ocr_data',
        'license_ocr_data',
        'quality_checks',
        'fraud_checks',
        'verification_details',
        'extracted_name',
        'extracted_aadhaar',
        'extracted_license',
        'verified_at',
        'rejection_reason',
        'rejected_at',
        'rejected_by',
        'verified_by'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_verified' => 'boolean',
        'aadhaar_ocr_data' => 'array',
        'license_ocr_data' => 'array',
        'quality_checks' => 'array',
        'fraud_checks' => 'array',
        'verification_details' => 'array',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // Add any sensitive fields here if needed
    ];

    /**
     * Get the rental that owns the document.
     */
    public function rental()
    {
        return $this->belongsTo(Rental::class);
    }

    /**
     * Get the user who verified the document.
     */
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the user who rejected the document.
     */
    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Check if document is verified.
     */
    public function isVerified(): bool
    {
        return $this->is_verified === true;
    }

    /**
     * Check if document is rejected.
     */
    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }

    /**
     * Check if document is pending verification.
     */
    public function isPending(): bool
    {
        return $this->verification_status === 'pending' || (!$this->is_verified && !$this->rejected_at);
    }

    /**
     * Get OCR confidence score.
     */
    public function getOcrConfidenceAttribute(): float
    {
        $scores = [];
        
        if ($this->fraud_checks && isset($this->fraud_checks['aadhaar']['authenticity_score'])) {
            $scores[] = $this->fraud_checks['aadhaar']['authenticity_score'];
        }
        
        if ($this->fraud_checks && isset($this->fraud_checks['license']['authenticity_score'])) {
            $scores[] = $this->fraud_checks['license']['authenticity_score'];
        }
        
        if (empty($scores)) {
            return 0;
        }
        
        return round(array_sum($scores) / count($scores) * 100, 2);
    }

    /**
     * Get quality issues summary.
     */
    public function getQualityIssuesAttribute(): array
    {
        $issues = [];
        
        if ($this->quality_checks) {
            $checks = $this->quality_checks;
            
            if (isset($checks['aadhaar']['blur']) && $checks['aadhaar']['blur'] === true) {
                $issues[] = 'Aadhaar image is blurry';
            }
            if (isset($checks['aadhaar']['glare']) && $checks['aadhaar']['glare'] === true) {
                $issues[] = 'Aadhaar image has glare';
            }
            if (isset($checks['aadhaar']['partially_present']) && $checks['aadhaar']['partially_present'] === true) {
                $issues[] = 'Aadhaar document is partially present';
            }
            
            if (isset($checks['license']['blur']) && $checks['license']['blur'] === true) {
                $issues[] = 'License image is blurry';
            }
            if (isset($checks['license']['glare']) && $checks['license']['glare'] === true) {
                $issues[] = 'License image has glare';
            }
            if (isset($checks['license']['partially_present']) && $checks['license']['partially_present'] === true) {
                $issues[] = 'License document is partially present';
            }
        }
        
        return $issues;
    }

    /**
     * Get fraud detection summary.
     */
    public function getFraudIssuesAttribute(): array
    {
        $issues = [];
        
        if ($this->fraud_checks) {
            $fraud = $this->fraud_checks;
            
            if (isset($fraud['aadhaar']['is_screenshot']) && $fraud['aadhaar']['is_screenshot'] === true) {
                $issues[] = 'Aadhaar screenshot detected';
            }
            if (isset($fraud['aadhaar']['is_forged']) && $fraud['aadhaar']['is_forged'] === true) {
                $issues[] = 'Aadhaar appears forged';
            }
            if (isset($fraud['aadhaar']['is_photo_of_screen']) && $fraud['aadhaar']['is_photo_of_screen'] === true) {
                $issues[] = 'Aadhaar is photo of screen';
            }
            
            if (isset($fraud['license']['is_screenshot']) && $fraud['license']['is_screenshot'] === true) {
                $issues[] = 'License screenshot detected';
            }
            if (isset($fraud['license']['is_forged']) && $fraud['license']['is_forged'] === true) {
                $issues[] = 'License appears forged';
            }
            if (isset($fraud['license']['is_photo_of_screen']) && $fraud['license']['is_photo_of_screen'] === true) {
                $issues[] = 'License is photo of screen';
            }
        }
        
        return $issues;
    }

    /**
     * Get formatted aadhaar number (masked).
     */
    public function getMaskedAadhaarAttribute(): ?string
    {
        if (!$this->extracted_aadhaar) {
            return null;
        }
        
        $aadhaar = preg_replace('/[^0-9]/', '', $this->extracted_aadhaar);
        if (strlen($aadhaar) === 12) {
            return 'XXXX-XXXX-' . substr($aadhaar, -4);
        }
        
        return $this->extracted_aadhaar;
    }

    /**
     * Get formatted license number (masked).
     */
    public function getMaskedLicenseAttribute(): ?string
    {
        if (!$this->extracted_license) {
            return null;
        }
        
        $license = $this->extracted_license;
        if (strlen($license) > 8) {
            return substr($license, 0, 4) . 'XXXX' . substr($license, -4);
        }
        
        return $license;
    }

    /**
     * Get verification status badge class.
     */
    public function getStatusBadgeAttribute(): string
    {
        if ($this->is_verified) {
            return 'success';
        }
        
        if ($this->verification_status === 'rejected' || $this->rejected_at) {
            return 'danger';
        }
        
        return 'warning';
    }

    /**
     * Get verification status text.
     */
    public function getStatusTextAttribute(): string
    {
        if ($this->is_verified) {
            return 'Verified';
        }
        
        if ($this->verification_status === 'rejected' || $this->rejected_at) {
            return 'Rejected';
        }
        
        if ($this->verification_status === 'failed') {
            return 'Verification Failed';
        }
        
        return 'Pending Verification';
    }

    /**
     * Scope for verified documents.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope for unverified documents.
     */
    public function scopeUnverified($query)
    {
        return $query->where('is_verified', false);
    }

    /**
     * Scope for pending documents.
     */
    public function scopePending($query)
    {
        return $query->where('is_verified', false)
            ->whereNull('rejected_at')
            ->where('verification_status', '!=', 'failed');
    }

    /**
     * Scope for rejected documents.
     */
    public function scopeRejected($query)
    {
        return $query->whereNotNull('rejected_at')
            ->orWhere('verification_status', 'rejected');
    }

    /**
     * Scope for documents with high fraud risk.
     */
    public function scopeHighFraudRisk($query)
    {
        return $query->whereJsonContains('fraud_checks->aadhaar->is_forged', true)
            ->orWhereJsonContains('fraud_checks->license->is_forged', true)
            ->orWhereJsonContains('fraud_checks->aadhaar->is_screenshot', true)
            ->orWhereJsonContains('fraud_checks->license->is_screenshot', true);
    }

    /**
     * Get the full URL of aadhaar image.
     */
    public function getAadhaarUrlAttribute(): ?string
    {
        if ($this->aadhaar_image && Storage::disk('public')->exists($this->aadhaar_image)) {
            return asset('storage/' . $this->aadhaar_image);
        }
        
        return null;
    }

    /**
     * Get the full URL of license image.
     */
    public function getLicenseUrlAttribute(): ?string
    {
        if ($this->license_image && Storage::disk('public')->exists($this->license_image)) {
            return asset('storage/' . $this->license_image);
        }
        
        return null;
    }
}