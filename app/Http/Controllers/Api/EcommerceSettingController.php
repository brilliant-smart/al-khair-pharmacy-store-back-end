<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EcommerceSetting;
use Illuminate\Http\Request;

class EcommerceSettingController extends Controller
{
    /**
     * Get e-commerce settings
     */
    public function show()
    {
        return response()->json(EcommerceSetting::get());
    }

    /**
     * Update e-commerce settings
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'online_payment_enabled' => 'nullable|boolean',
            'delivery_enabled' => 'nullable|boolean',
            'guest_checkout_enabled' => 'nullable|boolean',
            'min_order_amount' => 'nullable|numeric|min:0',
            'terms_and_conditions' => 'nullable|string',
        ]);

        $settings = EcommerceSetting::first();
        $settings->update($validated);

        return response()->json([
            'message' => 'E-commerce settings updated successfully',
            'settings' => $settings,
        ]);
    }
}
