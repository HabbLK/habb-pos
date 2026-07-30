<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'business_type' => ['required', 'string'],
        ]);

        $categories = Category::query()
            ->where('business_type', $request->string('business_type'))
            ->orderBy('sort_order')
            ->get();

        return CategoryResource::collection($categories);
    }
}
