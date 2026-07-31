<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $purchases = Purchase::with(['supplier', 'items.product'])
            ->when($request->filled('business_type'), fn ($q) => $q->where('business_type', $request->input('business_type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['data' => $purchases]);
    }

    /** Create a pending purchase order — stock isn't added until it's received. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'business_type' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $purchase = DB::transaction(function () use ($data) {
            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'],
                'business_type' => $data['business_type'],
                'status' => 'pending',
            ]);

            $total = 0;
            foreach ($data['items'] as $line) {
                $lineTotal = round($line['qty'] * $line['unit_cost'], 2);
                $total += $lineTotal;
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $line['product_id'],
                    'qty' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => $lineTotal,
                ]);
            }
            $purchase->update(['total_cost' => $total]);

            return $purchase;
        });

        return response()->json(['data' => $purchase->load('items.product', 'supplier')], 201);
    }

    /** Mark received: adds each line's qty to product stock and updates cost_price. */
    public function receive(Purchase $purchase)
    {
        if ($purchase->status === 'received') {
            throw ValidationException::withMessages(['status' => 'This purchase was already received.']);
        }

        DB::transaction(function () use ($purchase) {
            foreach ($purchase->items()->with('product')->get() as $item) {
                if (! $item->product->isUnlimitedStock()) {
                    $item->product->increment('stock', $item->qty);
                }
                $item->product->update(['cost_price' => $item->unit_cost]);
            }

            $purchase->update(['status' => 'received', 'received_at' => now()]);
        });

        return response()->json(['data' => $purchase->fresh()->load('items.product', 'supplier')]);
    }
}
