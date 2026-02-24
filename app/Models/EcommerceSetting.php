<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EcommerceSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'enabled',
        'online_payment_enabled',
        'delivery_enabled',
        'guest_checkout_enabled',
        'min_order_amount',
        'terms_and_conditions',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'online_payment_enabled' => 'boolean',
        'delivery_enabled' => 'boolean',
        'guest_checkout_enabled' => 'boolean',
        'min_order_amount' => 'decimal:2',
    ];

    public static function get()
    {
        return self::first() ?? self::create([
            'enabled' => false,
            'online_payment_enabled' => false,
            'delivery_enabled' => false,
            'guest_checkout_enabled' => true,
            'min_order_amount' => 0,
        ]);
    }
}
