<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\EcommerceSetting;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Get cart items
     */
    public function index(Request $request)
    {
        $settings = EcommerceSetting::get();
        if (!$settings->enabled) {
            return response()->json(['message' => 'E-commerce is currently disabled'], 503);
        }

        $customerId = auth('sanctum')->id();
        $sessionId = $request->header('X-Session-ID');

        $query = CartItem::with('product');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return response()->json(['items' => [], 'total' => 0]);
        }

        $items = $query->get();
        $total = $items->sum(fn($item) => $item->quantity * $item->unit_price);

        return response()->json([
            'items' => $items,
            'total' => $total,
            'count' => $items->sum('quantity'),
        ]);
    }

    /**
     * Add item to cart
     */
    public function store(Request $request)
    {
        $settings = EcommerceSetting::get();
        if (!$settings->enabled) {
            return response()->json(['message' => 'E-commerce is currently disabled'], 503);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        if (!$product->is_active) {
            return response()->json(['message' => 'Product is not available'], 422);
        }

        if ($product->stock_quantity < $validated['quantity']) {
            return response()->json(['message' => 'Insufficient stock'], 422);
        }

        $customerId = auth('sanctum')->id();
        $sessionId = $request->header('X-Session-ID');

        $cartItem = CartItem::updateOrCreate(
            [
                'customer_id' => $customerId,
                'session_id' => $customerId ? null : $sessionId,
                'product_id' => $validated['product_id'],
            ],
            [
                'quantity' => $validated['quantity'],
                'unit_price' => $product->price,
            ]
        );

        return response()->json([
            'message' => 'Item added to cart',
            'item' => $cartItem->load('product'),
        ], 201);
    }

    /**
     * Update cart item
     */
    public function update(Request $request, CartItem $cartItem)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        if ($cartItem->product->stock_quantity < $validated['quantity']) {
            return response()->json(['message' => 'Insufficient stock'], 422);
        }

        $cartItem->update(['quantity' => $validated['quantity']]);

        return response()->json([
            'message' => 'Cart updated',
            'item' => $cartItem->load('product'),
        ]);
    }

    /**
     * Remove cart item
     */
    public function destroy(CartItem $cartItem)
    {
        $cartItem->delete();

        return response()->json(['message' => 'Item removed from cart']);
    }

    /**
     * Clear cart
     */
    public function clear(Request $request)
    {
        $customerId = auth('sanctum')->id();
        $sessionId = $request->header('X-Session-ID');

        $query = CartItem::query();

        if ($customerId) {
            $query->where('customer_id', $customerId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        }

        $query->delete();

        return response()->json(['message' => 'Cart cleared']);
    }
}
