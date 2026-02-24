<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CRMController extends Controller
{
    // CRM Settings
    public function getSettings()
    {
        return response()->json(DB::table('crm_settings')->first());
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'loyalty_program_enabled' => 'nullable|boolean',
            'points_per_currency' => 'nullable|integer|min:1',
            'currency_per_point' => 'nullable|numeric|min:0',
        ]);

        DB::table('crm_settings')->update($validated);
        return response()->json(['message' => 'CRM settings updated']);
    }

    // Coupons
    public function getCoupons(Request $request)
    {
        $coupons = DB::table('coupons')->latest()->paginate(15);
        return response()->json($coupons);
    }

    public function createCoupon(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'name' => 'required|string',
            'type' => 'required|in:percentage,fixed_amount,free_shipping',
            'value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after:valid_from',
        ]);

        $id = DB::table('coupons')->insertGetId(array_merge($validated, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return response()->json(['message' => 'Coupon created', 'id' => $id], 201);
    }

    // Customer Segments
    public function getSegments()
    {
        $segments = DB::table('customer_segments')->get();
        return response()->json($segments);
    }

    public function createSegment(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'criteria' => 'required|array',
        ]);

        $id = DB::table('customer_segments')->insertGetId([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'criteria' => json_encode($validated['criteria']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Segment created', 'id' => $id], 201);
    }

    // Loyalty
    public function getLoyaltyPoints(Request $request)
    {
        $customerId = auth('sanctum')->id();
        $loyalty = DB::table('customer_loyalty_points')->where('customer_id', $customerId)->first();
        return response()->json($loyalty ?? ['points' => 0, 'tier' => 'bronze']);
    }

    public function getLoyaltyTransactions(Request $request)
    {
        $customerId = auth('sanctum')->id();
        $transactions = DB::table('loyalty_transactions')
            ->where('customer_id', $customerId)
            ->latest()
            ->paginate(15);
        return response()->json($transactions);
    }
}
