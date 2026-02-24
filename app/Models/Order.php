<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number', 'customer_id', 'customer_name', 'customer_email', 'customer_phone',
        'status', 'subtotal', 'tax', 'shipping_fee', 'discount', 'total_amount',
        'payment_method', 'payment_status', 'amount_paid', 'payment_reference', 'payment_date',
        'requires_delivery', 'delivery_address_id', 'delivery_status', 'tracking_number', 
        'delivery_date', 'notes', 'customer_notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'payment_date' => 'datetime',
        'delivery_date' => 'datetime',
        'requires_delivery' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function deliveryAddress()
    {
        return $this->belongsTo(CustomerAddress::class, 'delivery_address_id');
    }

    public static function generateOrderNumber(): string
    {
        $year = date('Y');
        $lastOrder = self::whereYear('created_at', $year)->orderBy('id', 'desc')->first();

        if ($lastOrder && preg_match('/ORD-' . $year . '-(\d+)/', $lastOrder->order_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return 'ORD-' . $year . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
