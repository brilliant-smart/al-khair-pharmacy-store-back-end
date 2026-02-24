<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Models\Order;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    /**
     * Get product reviews
     */
    public function index(Request $request, $productId)
    {
        $query = ProductReview::where('product_id', $productId)
            ->where('is_approved', true)
            ->with('customer');

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        $reviews = $query->latest()->paginate($request->input('per_page', 10));

        $averageRating = ProductReview::where('product_id', $productId)
            ->where('is_approved', true)
            ->avg('rating');

        $ratingBreakdown = ProductReview::where('product_id', $productId)
            ->where('is_approved', true)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->get()
            ->pluck('count', 'rating');

        return response()->json([
            'reviews' => $reviews,
            'average_rating' => round($averageRating, 1),
            'total_reviews' => ProductReview::where('product_id', $productId)->where('is_approved', true)->count(),
            'rating_breakdown' => $ratingBreakdown,
        ]);
    }

    /**
     * Create review
     */
    public function store(Request $request)
    {
        $customerId = auth('sanctum')->id();

        if (!$customerId) {
            return response()->json(['message' => 'Login required'], 401);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'order_id' => 'nullable|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'review' => 'nullable|string',
        ]);

        // Check if customer has already reviewed this product
        $existingReview = ProductReview::where('customer_id', $customerId)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existingReview) {
            return response()->json(['message' => 'You have already reviewed this product'], 422);
        }

        // Check if verified purchase
        $isVerifiedPurchase = false;
        if (isset($validated['order_id'])) {
            $order = Order::where('id', $validated['order_id'])
                ->where('customer_id', $customerId)
                ->whereHas('items', function ($query) use ($validated) {
                    $query->where('product_id', $validated['product_id']);
                })
                ->first();

            $isVerifiedPurchase = (bool) $order;
        }

        $review = ProductReview::create([
            'product_id' => $validated['product_id'],
            'customer_id' => $customerId,
            'order_id' => $validated['order_id'] ?? null,
            'rating' => $validated['rating'],
            'title' => $validated['title'] ?? null,
            'review' => $validated['review'] ?? null,
            'is_verified_purchase' => $isVerifiedPurchase,
            'is_approved' => false, // Requires admin approval
        ]);

        return response()->json([
            'message' => 'Review submitted successfully. It will be published after approval.',
            'review' => $review,
        ], 201);
    }
}
