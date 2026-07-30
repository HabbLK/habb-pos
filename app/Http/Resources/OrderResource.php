<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_type' => $this->business_type,
            'status' => $this->status,
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->name,
                'unit_price' => (float) $item->unit_price,
                'qty' => $item->qty,
                'line_total' => (float) $item->line_total,
            ]),
            'subtotal' => (float) $this->subtotal,
            'discount_percent' => (float) $this->discount_percent,
            'discount_amount' => (float) $this->discount_amount,
            'tax_rate' => (float) $this->tax_rate,
            'tax_total' => (float) $this->tax_total,
            'total' => (float) $this->total,
            'payment_method' => $this->payment_method,
            'tendered' => $this->tendered !== null ? (float) $this->tendered : null,
            'change_due' => $this->change_due !== null ? (float) $this->change_due : null,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
