<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class HabbPosSeeder extends Seeder
{
    /**
     * Mirrors the CATALOG object in habb-pos.html so the frontend and API
     * agree on ids/names/prices as soon as you point one at the other.
     */
    public function run(): void
    {
        $catalog = [
            'retail' => [
                'categories' => [
                    ['slug' => 'drinks', 'name' => 'Drinks', 'icon' => '🥤'],
                    ['slug' => 'snacks', 'name' => 'Snacks', 'icon' => '🍪'],
                    ['slug' => 'home', 'name' => 'Home', 'icon' => '🧺'],
                    ['slug' => 'stationery', 'name' => 'Stationery', 'icon' => '✏️'],
                ],
                'products' => [
                    ['name' => 'Sparkling Water 500ml', 'cat' => 'drinks', 'price' => 1.80, 'stock' => 42, 'emoji' => '🥤'],
                    ['name' => 'Ceylon Iced Tea', 'cat' => 'drinks', 'price' => 2.20, 'stock' => 18, 'emoji' => '🧋'],
                    ['name' => 'Coconut Water', 'cat' => 'drinks', 'price' => 2.50, 'stock' => 6, 'emoji' => '🥥'],
                    ['name' => 'Salt & Pepper Chips', 'cat' => 'snacks', 'price' => 1.40, 'stock' => 30, 'emoji' => '🍟'],
                    ['name' => 'Choc Chip Cookies', 'cat' => 'snacks', 'price' => 3.10, 'stock' => 22, 'emoji' => '🍪'],
                    ['name' => 'Mixed Nuts 100g', 'cat' => 'snacks', 'price' => 3.90, 'stock' => 9, 'emoji' => '🥜'],
                    ['name' => 'Cotton Tote Bag', 'cat' => 'home', 'price' => 6.50, 'stock' => 14, 'emoji' => '🧺'],
                    ['name' => 'Scented Candle', 'cat' => 'home', 'price' => 8.00, 'stock' => 5, 'emoji' => '🕯️'],
                    ['name' => 'Notebook A5', 'cat' => 'stationery', 'price' => 2.75, 'stock' => 26, 'emoji' => '📓'],
                    ['name' => 'Gel Pen Set', 'cat' => 'stationery', 'price' => 3.25, 'stock' => 19, 'emoji' => '🖊️'],
                ],
            ],
            'cafe' => [
                'categories' => [
                    ['slug' => 'hot', 'name' => 'Hot drinks', 'icon' => '☕'],
                    ['slug' => 'cold', 'name' => 'Cold drinks', 'icon' => '🧊'],
                    ['slug' => 'food', 'name' => 'Food', 'icon' => '🥐'],
                    ['slug' => 'desserts', 'name' => 'Desserts', 'icon' => '🍰'],
                ],
                'products' => [
                    ['name' => 'Flat White', 'cat' => 'hot', 'price' => 3.20, 'stock' => 999, 'emoji' => '☕'],
                    ['name' => 'Masala Chai', 'cat' => 'hot', 'price' => 2.60, 'stock' => 999, 'emoji' => '🍵'],
                    ['name' => 'Hot Chocolate', 'cat' => 'hot', 'price' => 3.50, 'stock' => 999, 'emoji' => '🍫'],
                    ['name' => 'Iced Latte', 'cat' => 'cold', 'price' => 3.80, 'stock' => 999, 'emoji' => '🧊'],
                    ['name' => 'Fresh Lime Soda', 'cat' => 'cold', 'price' => 2.90, 'stock' => 999, 'emoji' => '🥤'],
                    ['name' => 'Butter Croissant', 'cat' => 'food', 'price' => 2.90, 'stock' => 11, 'emoji' => '🥐'],
                    ['name' => 'Chicken Sandwich', 'cat' => 'food', 'price' => 5.40, 'stock' => 8, 'emoji' => '🥪'],
                    ['name' => 'Egg Hoppers (2pc)', 'cat' => 'food', 'price' => 4.20, 'stock' => 4, 'emoji' => '🍳'],
                    ['name' => 'Chocolate Brownie', 'cat' => 'desserts', 'price' => 3.60, 'stock' => 7, 'emoji' => '🍫'],
                    ['name' => 'Watalappan Cup', 'cat' => 'desserts', 'price' => 3.90, 'stock' => 6, 'emoji' => '🍮'],
                ],
            ],
            'service' => [
                'categories' => [
                    ['slug' => 'hair', 'name' => 'Hair', 'icon' => '💇'],
                    ['slug' => 'nails', 'name' => 'Nails', 'icon' => '💅'],
                    ['slug' => 'repair', 'name' => 'Repairs', 'icon' => '🔧'],
                    ['slug' => 'addons', 'name' => 'Add-ons', 'icon' => '✨'],
                ],
                'products' => [
                    ['name' => 'Haircut - Classic', 'cat' => 'hair', 'price' => 8.00, 'stock' => 999, 'emoji' => '💇'],
                    ['name' => 'Hair Colour', 'cat' => 'hair', 'price' => 22.00, 'stock' => 999, 'emoji' => '🎨'],
                    ['name' => 'Manicure', 'cat' => 'nails', 'price' => 10.00, 'stock' => 999, 'emoji' => '💅'],
                    ['name' => 'Pedicure', 'cat' => 'nails', 'price' => 12.00, 'stock' => 999, 'emoji' => '🦶'],
                    ['name' => 'Phone Screen Fix', 'cat' => 'repair', 'price' => 35.00, 'stock' => 999, 'emoji' => '📱'],
                    ['name' => 'Battery Replace', 'cat' => 'repair', 'price' => 18.00, 'stock' => 999, 'emoji' => '🔋'],
                    ['name' => 'Hot Towel', 'cat' => 'addons', 'price' => 2.00, 'stock' => 999, 'emoji' => '♨️'],
                    ['name' => 'Head Massage', 'cat' => 'addons', 'price' => 5.00, 'stock' => 999, 'emoji' => '💆'],
                ],
            ],
        ];

        foreach ($catalog as $businessType => $group) {
            $categoryIds = [];

            foreach ($group['categories'] as $order => $cat) {
                $category = Category::updateOrCreate(
                    ['business_type' => $businessType, 'slug' => $cat['slug']],
                    ['name' => $cat['name'], 'icon' => $cat['icon'], 'sort_order' => $order]
                );
                $categoryIds[$cat['slug']] = $category->id;
            }

            foreach ($group['products'] as $product) {
                Product::updateOrCreate(
                    ['business_type' => $businessType, 'name' => $product['name']],
                    [
                        'category_id' => $categoryIds[$product['cat']],
                        'price' => $product['price'],
                        'stock' => $product['stock'],
                        'emoji' => $product['emoji'],
                        'active' => true,
                    ]
                );
            }
        }
    }
}
