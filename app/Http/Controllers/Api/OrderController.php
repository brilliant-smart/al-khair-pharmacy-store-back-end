<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\EcommerceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Get customer orders
     */
    public function index(Request $request)
    {
        $query = Order::with('items.product');

        if ($customerId = auth('sanctum')->id()) {
            $query->where('customer_id', $customerId);
        } else {
            return response()->json(['orders' => []]);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($orders);
    }

    /**
     * Create order
     */
    public function store(Request $request)
    {
        $settings = EcommerceSetting::get();
        if (!$settings->enabled) {
            return response()->json(['message' => 'E-commerce is currently disabled'], 503);
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|string|max:20',
            'payment_method' => 'required|in:cash,bank_transfer,card,online',
            'requires_delivery' => 'nullable|boolean',
            'delivery_address_id' => 'required_if:requires_delivery,true|exists:customer_addresses,id',
            'customer_notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if (!$settings->online_payment_enabled && $validated['payment_method'] === 'online') {
            return response()->json(['message' => 'Online payment is currently disabled'], 422);
        }

        if (!$settings->delivery_enabled && ($validated['requires_delivery'] ?? false)) {
            return response()->json(['message' => 'Delivery service is currently disabled'], 422);
        }

        return DB::transaction(function () use ($validated, $settings) {
            $subtotal = 0;
            $orderItems = [];

            // Validate and calculate totals
            foreach ($validated['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);

                if (!$product->is_active) {
                    throw new \Exception("Product {$product->name} is not available");
                }

                if ($product->stock_quantity < $itemData['quantity']) {
                    throw new \Exception("Insufficient stock for {$product->name}");
                }

                $itemTotal = $product->price * $itemData['quantity'];
                $subtotal += $itemTotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $product->price,
                    'total_price' => $itemTotal,
                ];
            }

            if ($subtotal < $settings->min_order_amount) {
                throw new \Exception("Minimum order amount is ₦" . number_format($settings->min_order_amount, 2));
            }

            // Calculate shipping
            $shippingFee = ($validated['requires_delivery'] ?? false) ? 0 : 0; // TODO: Implement shipping calculation

            $totalAmount = $subtotal + $shippingFee;

            // Create order
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'customer_id' => auth('sanctum')->id(),
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'],
                'status' => 'pending',
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'total_amount' => $totalAmount,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'unpaid',
                'requires_delivery' => $validated['requires_delivery'] ?? false,
                'delivery_address_id' => $validated['delivery_address_id'] ?? null,
                'delivery_status' => ($validated['requires_delivery'] ?? false) ? 'pending' : null,
                'customer_notes' => $validated['customer_notes'] ?? null,
            ]);

            // Create order items and deduct stock
            foreach ($orderItems as $itemData) {
                $order->items()->create($itemData);
                
                Product::where('id', $itemData['product_id'])
                    ->decrement('stock_quantity', $itemData['quantity']);
            }

            // Clear cart if customer is logged in
            if (auth('sanctum')->id()) {
                CartItem::where('customer_id', auth('sanctum')->id())->delete();
            }

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order->load('items.product'),
            ], 201);
        });
    }

    /**
     * Get order details
     */
    public function show(Order $order)
    {
        // Check authorization
        if (auth('sanctum')->id() && $order->customer_id !== auth('sanctum')->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($order->load(['items.product', 'deliveryAddress']));
    }

    /**
     * Cancel order
     */
    public function cancel(Order $order)
    {
        if (auth('sanctum')->id() && $order->customer_id !== auth('sanctum')->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'Order cannot be cancelled'], 422);
        }

        DB::transaction(function () use ($order) {
            // Restore stock
            foreach ($order->items as $item) {
                Product::where('id', $item->product_id)
                    ->increment('stock_quantity', $item->quantity);
            }

            $order->update(['status' => 'cancelled']);
        });

        return response()->json(['message' => 'Order cancelled successfully']);
    }
}
