<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            HabbPosSeeder::class,
        ]);

        // Demo accounts — change these passwords before going anywhere near
        // production. Role gates the admin-only endpoints (Inventory,
        // Purchases, Expenses, Reports).
        User::updateOrCreate(
            ['email' => 'admin@habb.lk'],
            ['name' => 'HABB Admin', 'password' => Hash::make('password'), 'role' => 'admin']
        );
        User::updateOrCreate(
            ['email' => 'cashier@habb.lk'],
            ['name' => 'HABB Cashier', 'password' => Hash::make('password'), 'role' => 'cashier']
        );

        Supplier::updateOrCreate(
            ['name' => 'Sample Distributors (Pvt) Ltd'],
            ['phone' => '+94 11 234 5678', 'email' => 'orders@sampledist.lk']
        );

        foreach (['Rent', 'Utilities', 'Supplies', 'Wages', 'Other'] as $name) {
            ExpenseCategory::updateOrCreate(['name' => $name]);
        }
    }
}
