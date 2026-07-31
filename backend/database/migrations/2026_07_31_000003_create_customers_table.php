<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->decimal('credit_balance', 10, 2)->default(0); // running tab, e.g. "Pay Later"
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('register_session_id')
                ->constrained()->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('completed_at');
            $table->string('void_reason')->nullable()->after('voided_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['voided_at', 'void_reason']);
        });
        Schema::dropIfExists('customers');
    }
};
