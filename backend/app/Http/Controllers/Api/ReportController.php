<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /** Headline numbers for the admin dashboard. */
    public function summary(Request $request)
    {
        $businessType = $request->query('business_type');
        $from = $request->query('date_from', now()->startOfDay()->toDateString());
        $to = $request->query('date_to', now()->endOfDay()->toDateString());

        $orders = Order::where('status', 'completed')
            ->when($businessType, fn ($q) => $q->where('business_type', $businessType))
            ->whereBetween('completed_at', ["$from 00:00:00", "$to 23:59:59"]);

        $orderCount = (clone $orders)->count();
        $revenue = (clone $orders)->sum('total');
        $discountGiven = (clone $orders)->sum('discount_amount');
        $taxCollected = (clone $orders)->sum('tax_total');

        $expenses = Expense::when($businessType, fn ($q) => $q->where('business_type', $businessType))
            ->whereBetween('spent_on', [$from, $to])
            ->sum('amount');

        return response()->json(['data' => [
            'date_from' => $from,
            'date_to' => $to,
            'order_count' => $orderCount,
            'revenue' => (float) $revenue,
            'average_ticket' => $orderCount > 0 ? round($revenue / $orderCount, 2) : 0,
            'discount_given' => (float) $discountGiven,
            'tax_collected' => (float) $taxCollected,
            'expenses' => (float) $expenses,
            'net' => round((float) $revenue - (float) $expenses, 2),
        ]]);
    }

    /** Best-selling products by quantity, within the same date range. */
    public function topProducts(Request $request)
    {
        $businessType = $request->query('business_type');
        $from = $request->query('date_from', now()->startOfMonth()->toDateString());
        $to = $request->query('date_to', now()->endOfDay()->toDateString());

        $rows = OrderItem::query()
            ->selectRaw('order_items.name, SUM(order_items.qty) as qty_sold, SUM(order_items.line_total) as revenue')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', 'completed')
            ->when($businessType, fn ($q) => $q->where('orders.business_type', $businessType))
            ->whereBetween('orders.completed_at', ["$from 00:00:00", "$to 23:59:59"])
            ->groupBy('order_items.name')
            ->orderByDesc('qty_sold')
            ->limit(10)
            ->get();

        return response()->json(['data' => $rows]);
    }

    /** Products running low so restocking can happen before they hit zero. */
    public function lowStock(Request $request)
    {
        $businessType = $request->query('business_type');
        $threshold = (int) $request->query('threshold', 10);

        $products = Product::query()
            ->where('active', true)
            ->where('stock', '<', min($threshold, 99)) // below 100 is a "tracked" item, see isUnlimitedStock()
            ->when($businessType, fn ($q) => $q->where('business_type', $businessType))
            ->orderBy('stock')
            ->get(['id', 'name', 'business_type', 'stock', 'emoji']);

        return response()->json(['data' => $products]);
    }
}
