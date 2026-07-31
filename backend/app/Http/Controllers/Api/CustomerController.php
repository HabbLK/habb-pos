<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%')
                ->orWhere('phone', 'like', '%'.$request->input('search').'%'))
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json(['data' => $customers]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
        ]);

        $customer = Customer::create($data);

        return response()->json(['data' => $customer], 201);
    }

    public function show(Customer $customer)
    {
        return response()->json([
            'data' => $customer,
            'recent_orders' => $customer->orders()->latest()->limit(20)->get(['id', 'total', 'status', 'completed_at']),
        ]);
    }
}
