<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'business_type' => ['required', 'string'],
            'category' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
        ]);

        $products = Product::query()
            ->with('category')
            ->where('business_type', $request->string('business_type'))
            ->where('active', true)
            ->when($request->filled('category') && $request->input('category') !== 'all', function ($query) use ($request) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $request->input('category')));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%'.$request->input('search').'%');
            })
            ->orderBy('name')
            ->get();

        return ProductResource::collection($products);
    }

    /** Create a new product (admin — Inventory screen). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'business_type' => ['required', 'string', 'in:retail,cafe,service'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'emoji' => ['nullable', 'string', 'max:10'],
        ]);

        return new ProductResource(Product::create($data + ['active' => true]));
    }

    /** Edit a product's details (admin — Inventory screen). */
    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku,'.$product->id],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'emoji' => ['nullable', 'string', 'max:10'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $product->update($data);

        return new ProductResource($product);
    }

    /**
     * Manually adjust stock (e.g. stocktake correction, breakage, theft) —
     * separate from purchases, which add stock via receiving an order.
     */
    public function adjustStock(Request $request, Product $product)
    {
        $data = $request->validate([
            'delta' => ['required', 'integer'], // positive to add, negative to remove
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($product->isUnlimitedStock()) {
            throw ValidationException::withMessages([
                'delta' => 'This product doesn\'t track stock.',
            ]);
        }

        $newStock = max(0, $product->stock + $data['delta']);
        $product->update(['stock' => $newStock]);

        return new ProductResource($product);
    }
}
