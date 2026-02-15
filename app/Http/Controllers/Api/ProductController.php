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

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Support custom limit for homepage (without pagination)
        if ($request->filled('limit')) {
            $limit = min((int) $request->limit, 50); // Max 50 items
            return response()->json(
                $query->latest()->limit($limit)->get()
            );
        }

        return response()->json(
            $query->latest()->paginate(12)
        );
    }

    /** Public: View single product */
    public function show(Product $product)
    {
        return response()->json($product->load('department'));
    }

    /** Protected: Create product */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'slug'          => ['required', 'string', 'max:255', 'unique:products,slug'],
            'description'   => ['nullable', 'string'],
            'price'         => ['required', 'numeric', 'min:0'],
            'department_id' => ['required', 'exists:departments,id'],
            'image'         => ['nullable', 'image', 'max:2048'],
            'is_active'     => ['boolean'],
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
            'name'        => ['sometimes', 'string', 'max:255'],
            'slug'        => ['sometimes', 'string', 'max:255', 'unique:products,slug,' . $product->id],
            'description' => ['nullable', 'string'],
            'price'       => ['sometimes', 'numeric', 'min:0'],
            'image'       => ['nullable', 'image', 'max:2048'],
            'is_active'   => ['boolean'],
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
