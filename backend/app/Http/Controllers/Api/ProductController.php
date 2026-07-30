<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

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
}
