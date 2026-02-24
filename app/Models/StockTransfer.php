<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_number',
        'product_id',
        'from_department_id',
        'to_department_id',
        'quantity',
        'batch_number',
        'requested_by',
        'approved_by',
        'received_by',
        'status',
        'requested_at',
        'approved_at',
        'received_at',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    /**
     * Get the product being transferred
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the source department
     */
    public function fromDepartment()
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    /**
     * Get the destination department
     */
    public function toDepartment()
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    /**
     * Get the user who requested the transfer
     */
    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved the transfer
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who received the transfer
     */
    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Generate next transfer number
     */
    public static function generateTransferNumber(): string
    {
        $year = date('Y');
        $lastTransfer = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastTransfer && preg_match('/ST-' . $year . '-(\d+)/', $lastTransfer->transfer_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return 'ST-' . $year . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Scope for pending transfers
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved transfers
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for in-transit transfers
     */
    public function scopeInTransit($query)
    {
        return $query->where('status', 'in_transit');
    }
}
