<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_type' => $this->business_type,
            'category' => $this->category?->slug,
            'name' => $this->name,
            'sku' => $this->sku,
            'price' => (float) $this->price,
            'cost_price' => $this->cost_price !== null ? (float) $this->cost_price : null,
            'stock' => $this->stock,
            'unlimited_stock' => $this->isUnlimitedStock(),
            'emoji' => $this->emoji,
        ];
    }
}
