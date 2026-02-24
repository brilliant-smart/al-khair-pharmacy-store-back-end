<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prescription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'prescription_number',
        'patient_name',
        'patient_phone',
        'patient_address',
        'patient_dob',
        'doctor_name',
        'doctor_license',
        'hospital',
        'prescription_date',
        'expiry_date',
        'status',
        'dispensed_by',
        'dispensed_at',
        'notes',
        'image_url',
    ];

    protected $casts = [
        'prescription_date' => 'date',
        'expiry_date' => 'date',
        'patient_dob' => 'date',
        'dispensed_at' => 'datetime',
    ];

    /**
     * Get the items for this prescription
     */
    public function items()
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    /**
     * Get the user who dispensed
     */
    public function dispensedBy()
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }

    /**
     * Generate prescription number
     */
    public static function generatePrescriptionNumber(): string
    {
        $year = date('Y');
        $lastPrescription = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastPrescription && preg_match('/RX-' . $year . '-(\d+)/', $lastPrescription->prescription_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return 'RX-' . $year . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Check if prescription is expired
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Check if fully dispensed
     */
    public function isFullyDispensed(): bool
    {
        return $this->items->every(function ($item) {
            return $item->dispensed_quantity >= $item->prescribed_quantity;
        });
    }

    /**
     * Scope for pending prescriptions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for dispensed prescriptions
     */
    public function scopeDispensed($query)
    {
        return $query->whereIn('status', ['dispensed', 'partially_dispensed']);
    }
}
