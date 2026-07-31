<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RegisterSession;
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
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->input('customer_id')))
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
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'payment_method' => ['nullable', 'string', 'in:Cash,Card,QR,Credit'],
            'tendered' => ['nullable', 'numeric', 'min:0'],
        ]);

        $order = DB::transaction(function () use ($data) {
            $order = Order::create([
                'business_type' => $data['business_type'],
                'status' => 'held', // finalised below if 'completed' was requested
                'discount_percent' => $data['discount_percent'] ?? 0,
                'customer_id' => $data['customer_id'] ?? null,
            ]);

            $this->syncItems($order, $data['items']);
            $order->recalculateTotals();
            $order->save();

            return $order;
        });

        if (($data['status'] ?? 'held') === 'completed') {
            $order = $this->finalize($request, $order, $data['payment_method'] ?? null, $data['tendered'] ?? null);
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
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($order, $data) {
            $order->items()->delete();
            $this->syncItems($order, $data['items']);
            $order->discount_percent = $data['discount_percent'] ?? $order->discount_percent;
            $order->customer_id = $data['customer_id'] ?? $order->customer_id;
            $order->recalculateTotals();
            $order->save();
        });

        return new OrderResource($order->load('items'));
    }

    /**
     * Cancel a held ticket. Completed sales use /orders/{order}/void instead,
     * since deleting a paid sale would break sales history and reporting.
     */
    public function destroy(Order $order)
    {
        if ($order->status !== 'held') {
            throw ValidationException::withMessages([
                'status' => 'Only held tickets can be cancelled. Use void for a completed sale.',
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
            'payment_method' => ['required', 'string', 'in:Cash,Card,QR,Credit'],
            'tendered' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($order->status !== 'held') {
            throw ValidationException::withMessages([
                'status' => 'This ticket is not awaiting payment.',
            ]);
        }

        $order = $this->finalize($request, $order, $data['payment_method'], $data['tendered'] ?? null);

        return new OrderResource($order->load('items'));
    }

    /**
     * Void a completed sale: restocks every line item and marks the order
     * voided with a reason. Full-order only for now — partial/line-level
     * returns are a good next addition once this is in use.
     */
    public function void(Request $request, Order $order)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($order->status !== 'completed') {
            throw ValidationException::withMessages([
                'status' => 'Only a completed sale can be voided.',
            ]);
        }

        DB::transaction(function () use ($order, $data) {
            foreach ($order->items()->with('product')->get() as $item) {
                if ($item->product && ! $item->product->isUnlimitedStock()) {
                    $item->product->increment('stock', $item->qty);
                }
            }

            if ($order->customer_id) {
                $order->customer()->decrement('loyalty_points', (int) floor((float) $order->total));
                if ($order->payment_method === 'Credit') {
                    $order->customer()->decrement('credit_balance', (float) $order->total);
                }
            }

            $order->update([
                'status' => 'voided',
                'voided_at' => now(),
                'void_reason' => $data['reason'],
            ]);
        });

        return new OrderResource($order->fresh()->load('items'));
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
     * Mark the ticket paid: validate cash cover, work out change, decrement
     * stock, attach the cashier's open register session (for cash
     * reconciliation), and award loyalty points / credit balance if a
     * customer is attached.
     */
    private function finalize(Request $request, Order $order, ?string $paymentMethod, ?float $tendered): Order
    {
        return DB::transaction(function () use ($request, $order, $paymentMethod, $tendered) {
            $order->refresh()->load('items.product', 'customer');

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

            if ($paymentMethod === 'Credit' && ! $order->customer_id) {
                throw ValidationException::withMessages([
                    'customer_id' => 'A customer must be attached to sell on credit.',
                ]);
            }

            foreach ($order->items as $item) {
                if ($item->product && ! $item->product->isUnlimitedStock()) {
                    $item->product->decrement('stock', $item->qty);
                }
            }

            if ($user = $request->user()) {
                $openSession = RegisterSession::where('user_id', $user->id)
                    ->where('business_type', $order->business_type)
                    ->where('status', 'open')
                    ->latest('opened_at')
                    ->first();
                $order->register_session_id = $openSession?->id;
            }

            $order->status = 'completed';
            $order->payment_method = $paymentMethod;
            $order->completed_at = now();
            $order->save();

            if ($order->customer) {
                // 1 loyalty point per whole currency unit spent.
                $order->customer->increment('loyalty_points', (int) floor((float) $order->total));
                if ($paymentMethod === 'Credit') {
                    $order->customer->increment('credit_balance', (float) $order->total);
                }
            }

            return $order;
        });
    }
}
