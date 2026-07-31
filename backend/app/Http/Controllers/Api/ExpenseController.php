<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses = Expense::with('category')
            ->when($request->filled('business_type'), fn ($q) => $q->where('business_type', $request->input('business_type')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('spent_on', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('spent_on', '<=', $request->input('date_to')))
            ->orderByDesc('spent_on')
            ->limit(200)
            ->get();

        return response()->json(['data' => $expenses]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'business_type' => ['required', 'string'],
            'expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:255'],
            'spent_on' => ['required', 'date'],
        ]);

        return response()->json(['data' => Expense::create($data)->load('category')], 201);
    }

    public function categories()
    {
        return response()->json(['data' => ExpenseCategory::orderBy('name')->get()]);
    }
}
