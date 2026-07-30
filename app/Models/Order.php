<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_type', 'status', 'subtotal', 'discount_percent',
        'discount_amount', 'tax_rate', 'tax_total', 'total',
        'payment_method', 'tendered', 'change_due', 'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Recompute subtotal / discount / tax / total from the current line
     * items. This is the single source of truth for money math — the
     * frontend's numbers are only ever a preview.
     */
    public function recalculateTotals(): void
    {
        $subtotal = $this->items->sum('line_total');
        $discountAmount = round($subtotal * ((float) $this->discount_percent / 100), 2);
        $taxable = $subtotal - $discountAmount;
        $taxTotal = round($taxable * (float) $this->tax_rate, 2);

        $this->subtotal = $subtotal;
        $this->discount_amount = $discountAmount;
        $this->tax_total = $taxTotal;
        $this->total = round($taxable + $taxTotal, 2);
    }
}
