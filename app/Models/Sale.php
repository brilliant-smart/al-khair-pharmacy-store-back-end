<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_number',
        'department_id',
        'cashier_id',
        'sale_type',
        'payment_status',
        'subtotal',
        'vat_amount',
        'discount_amount',
        'total_amount',
        'amount_paid',
        'amount_due',
        'cost_of_goods_sold',
        'gross_profit',
        'customer_name',
        'customer_phone',
        'notes',
        'sale_date',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'cost_of_goods_sold' => 'decimal:2',
        'gross_profit' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Accessors
     */
    public function getProfitMarginAttribute(): float
    {
        return $this->total_amount > 0 
            ? ($this->gross_profit / $this->total_amount) * 100 
            : 0;
    }

    /**
     * Scopes
     */
    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeByCashier($query, $cashierId)
    {
        return $query->where('cashier_id', $cashierId);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('sale_date', [$startDate, $endDate]);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', '!=', 'paid');
    }

    /**
     * Generate Sale Number
     */
    public static function generateSaleNumber(): string
    {
        $year = date('Y');
        $lastSale = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastSale ? (int)substr($lastSale->sale_number, -4) + 1 : 1;

        return 'SALE-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
