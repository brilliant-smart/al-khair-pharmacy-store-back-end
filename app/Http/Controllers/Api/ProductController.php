<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProductController extends Controller
{
    use AuthorizesRequests;

    /** Public: List products (optionally by department) */
    public function index(Request $request)
    {
        $query = Product::with('department')
            ->where('is_active', true);

        // Filter by department_id
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Filter by department_slug
        if ($request->filled('department_slug')) {
            $query->whereHas('department', function ($q) use ($request) {
                $q->where('slug', $request->department_slug);
            });
        }

        // Filter by featured status
        if ($request->filled('featured')) {
            $query->where('is_featured', $request->featured === 'true' || $request->featured === '1');
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'newest');
        switch ($sortBy) {
            case 'oldest':
                $query->oldest();
                break;
            case 'price-low':
                $query->orderBy('price', 'asc');
                break;
            case 'price-high':
                $query->orderBy('price', 'desc');
                break;
            case 'name-asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name-desc':
                $query->orderBy('name', 'desc');
                break;
            case 'newest':
            default:
                $query->latest();
                break;
        }

        // Support custom limit for homepage (without pagination)
        if ($request->filled('limit')) {
            $limit = min((int) $request->limit, 100); // Max 100 items
            return response()->json(
                $query->limit($limit)->get()
            );
        }

        return response()->json(
            $query->paginate(12)
        );
    }

    /** Public: View single product */
    public function show(Product $product)
    {
        return response()->json($product->load('department'));
    }

    /** Public: Search product by barcode */
    public function searchByBarcode(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string'
        ]);

        $product = Product::with('department')
            ->where('barcode', $request->barcode)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            return response()->json([
                'message' => 'Product not found with barcode: ' . $request->barcode
            ], 404);
        }

        return response()->json($product);
    }

    /** Protected: Create product */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'slug'                => ['required', 'string', 'max:255', 'unique:products,slug'],
            'sku'                 => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'barcode'             => ['nullable', 'string', 'max:100', 'unique:products,barcode'],
            'description'         => ['nullable', 'string'],
            'price'               => ['required', 'numeric', 'min:0'],
            'stock_quantity'      => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'department_id'       => ['required', 'exists:departments,id'],
            'image'               => ['nullable', 'image', 'max:2048'],
            'is_active'           => ['boolean'],
            'is_featured'         => ['boolean'],
            // WORLD-CLASS: Batch & Expiry Tracking
            'track_batch'         => ['boolean'],
            'track_expiry'        => ['boolean'],
            'batch_number'        => ['nullable', 'string', 'max:255'],
            'manufacturing_date'  => ['nullable', 'date', 'before_or_equal:today'],
            'expiry_date'         => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $department = Department::findOrFail($validated['department_id']);
        $this->authorize('create', [Product::class, $department]);

        if ($request->hasFile('image')) {
            $validated['image_url'] = $request->file('image')
                ->store('products', 'public');
        }

        $product = Product::create($validated);

        return response()->json($product->load('department'), 201);
    }

    /** Protected: Update product */
    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'name'                => ['sometimes', 'string', 'max:255'],
            'slug'                => ['sometimes', 'string', 'max:255', 'unique:products,slug,' . $product->id],
            'sku'                 => ['sometimes', 'string', 'max:100', 'unique:products,sku,' . $product->id],
            'barcode'             => ['sometimes', 'string', 'max:100', 'unique:products,barcode,' . $product->id],
            'description'         => ['nullable', 'string'],
            'price'               => ['sometimes', 'numeric', 'min:0'],
            'stock_quantity'      => ['sometimes', 'integer', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'image'               => ['nullable', 'image', 'max:2048'],
            'is_active'           => ['boolean'],
            'is_featured'         => ['boolean'],
            // WORLD-CLASS: Batch & Expiry Tracking
            'track_batch'         => ['boolean'],
            'track_expiry'        => ['boolean'],
            'batch_number'        => ['nullable', 'string', 'max:255'],
            'manufacturing_date'  => ['nullable', 'date', 'before_or_equal:today'],
            'expiry_date'         => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        if ($request->hasFile('image')) {
            if ($product->image_url) {
                Storage::disk('public')->delete($product->image_url);
            }

            $validated['image_url'] = $request->file('image')
                ->store('products', 'public');
        }

        $product->update($validated);

        return response()->json($product->load('department'));
    }

    /** Protected: Delete product */
    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        if ($product->image_url) {
            Storage::disk('public')->delete($product->image_url);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }
}
