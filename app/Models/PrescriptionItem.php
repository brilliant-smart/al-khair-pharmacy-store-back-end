<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrescriptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_id',
        'product_id',
        'prescribed_quantity',
        'dispensed_quantity',
        'dosage',
        'frequency',
        'duration_days',
        'instructions',
        'is_controlled_substance',
    ];

    protected $casts = [
        'prescribed_quantity' => 'integer',
        'dispensed_quantity' => 'integer',
        'duration_days' => 'integer',
        'is_controlled_substance' => 'boolean',
    ];

    /**
     * Get the prescription
     */
    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get remaining quantity to dispense
     */
    public function getRemainingQuantityAttribute(): int
    {
        return $this->prescribed_quantity - $this->dispensed_quantity;
    }

    /**
     * Check if fully dispensed
     */
    public function isFullyDispensed(): bool
    {
        return $this->dispensed_quantity >= $this->prescribed_quantity;
    }
}
