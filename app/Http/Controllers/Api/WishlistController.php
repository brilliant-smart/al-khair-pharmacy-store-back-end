<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Get wishlist
     */
    public function index(Request $request)
    {
        $customerId = auth('sanctum')->id();

        if (!$customerId) {
            return response()->json(['message' => 'Login required'], 401);
        }

        $wishlists = Wishlist::where('customer_id', $customerId)
            ->with('product')
            ->latest()
            ->get();

        return response()->json($wishlists);
    }

    /**
     * Add to wishlist
     */
    public function store(Request $request)
    {
        $customerId = auth('sanctum')->id();

        if (!$customerId) {
            return response()->json(['message' => 'Login required'], 401);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $wishlist = Wishlist::firstOrCreate([
            'customer_id' => $customerId,
            'product_id' => $validated['product_id'],
        ]);

        return response()->json([
            'message' => 'Added to wishlist',
            'wishlist' => $wishlist->load('product'),
        ], 201);
    }

    /**
     * Remove from wishlist
     */
    public function destroy(Wishlist $wishlist)
    {
        if ($wishlist->customer_id !== auth('sanctum')->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $wishlist->delete();

        return response()->json(['message' => 'Removed from wishlist']);
    }
}
