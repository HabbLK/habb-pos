<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * List tickets — used for the "held orders" drawer and for a basic
     * sales history / receipt lookup.
     */
    public function index(Request $request)
    {
        $orders = Order::query()
            ->with('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('business_type'), fn ($q) => $q->where('business_type', $request->input('business_type')))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return OrderResource::collection($orders);
    }

    public function show(Order $order)
    {
        return new OrderResource($order->load('items'));
    }

    /**
     * Create a new ticket — either parked ("held", from the Hold button)
     * or paid immediately ("completed", from Charge). Money is always
     * recalculated here from the current product prices; client-side
     * totals are only ever a preview.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'business_type' => ['required', 'string', 'in:retail,cafe,service'],
            'status' => ['nullable', 'string', 'in:held,completed'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'payment_method' => ['nullable', 'string', 'in:Cash,Card,QR'],
            'tendered' => ['nullable', 'numeric', 'min:0'],
        ]);

        $order = DB::transaction(function () use ($data) {
            $order = Order::create([
                'business_type' => $data['business_type'],
                'status' => 'held', // finalised below if 'completed' was requested
                'discount_percent' => $data['discount_percent'] ?? 0,
            ]);

            $this->syncItems($order, $data['items']);
            $order->recalculateTotals();
            $order->save();

            return $order;
        });

        if (($data['status'] ?? 'held') === 'completed') {
            $order = $this->finalize($order, $data['payment_method'] ?? null, $data['tendered'] ?? null);
        }

        return new OrderResource($order->load('items'));
    }

    /**
     * Replace the line items / discount on a held ticket (e.g. resumed
     * from the held-orders drawer and edited before charging).
     */
    public function update(Request $request, Order $order)
    {
        if ($order->status !== 'held') {
            throw ValidationException::withMessages([
                'status' => 'Only held tickets can be edited.',
            ]);
        }

        $data = $request->validate([
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($order, $data) {
            $order->items()->delete();
            $this->syncItems($order, $data['items']);
            $order->discount_percent = $data['discount_percent'] ?? $order->discount_percent;
            $order->recalculateTotals();
            $order->save();
        });

        return new OrderResource($order->load('items'));
    }

    /**
     * Cancel a held ticket. Completed sales are never deleted — void them
     * through a future /orders/{order}/void endpoint if you need that.
     */
    public function destroy(Order $order)
    {
        if ($order->status !== 'held') {
            throw ValidationException::withMessages([
                'status' => 'Only held tickets can be cancelled.',
            ]);
        }

        $order->delete();

        return response()->json(['message' => 'Ticket cancelled.']);
    }

    /**
     * Take payment on a held ticket: this is what the Charge button hits.
     */
    public function complete(Request $request, Order $order)
    {
        $data = $request->validate([
            'payment_method' => ['required', 'string', 'in:Cash,Card,QR'],
            'tendered' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($order->status !== 'held') {
            throw ValidationException::withMessages([
                'status' => 'This ticket is not awaiting payment.',
            ]);
        }

        $order = $this->finalize($order, $data['payment_method'], $data['tendered'] ?? null);

        return new OrderResource($order->load('items'));
    }

    /**
     * Snapshot each product's current name/price onto the order line, and
     * validate that limited-stock items aren't over-sold.
     */
    private function syncItems(Order $order, array $items): void
    {
        $products = Product::whereIn('id', collect($items)->pluck('product_id'))->get()->keyBy('id');

        foreach ($items as $line) {
            $product = $products->get($line['product_id']);

            if (! $product || ! $product->active) {
                throw ValidationException::withMessages([
                    'items' => "Product #{$line['product_id']} is not available.",
                ]);
            }

            if (! $product->isUnlimitedStock() && $product->stock < $line['qty']) {
                throw ValidationException::withMessages([
                    'items' => "Not enough stock for \"{$product->name}\" (only {$product->stock} left).",
                ]);
            }

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'unit_price' => $product->price,
                'qty' => $line['qty'],
                'line_total' => round($product->price * $line['qty'], 2),
            ]);
        }
    }

    /**
     * Mark the ticket paid: validate cash cover, work out change, and
     * decrement stock for items that track real inventory.
     */
    private function finalize(Order $order, ?string $paymentMethod, ?float $tendered): Order
    {
        return DB::transaction(function () use ($order, $paymentMethod, $tendered) {
            $order->refresh()->load('items.product');

            if ($paymentMethod === 'Cash') {
                if ($tendered === null || $tendered < (float) $order->total) {
                    throw ValidationException::withMessages([
                        'tendered' => 'Cash tendered is less than the total due.',
                    ]);
                }
                $order->tendered = $tendered;
                $order->change_due = round($tendered - (float) $order->total, 2);
            } else {
                $order->tendered = $order->total;
                $order->change_due = 0;
            }

            foreach ($order->items as $item) {
                if ($item->product && ! $item->product->isUnlimitedStock()) {
                    $item->product->decrement('stock', $item->qty);
                }
            }

            $order->status = 'completed';
            $order->payment_method = $paymentMethod;
            $order->completed_at = now();
            $order->save();

            return $order;
        });
    }
}
