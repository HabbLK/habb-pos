<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('register_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('business_type')->index();
            $table->decimal('opening_float', 10, 2)->default(0);
            $table->decimal('expected_cash', 10, 2)->nullable();  // computed at close: float + cash sales
            $table->decimal('closing_count', 10, 2)->nullable();   // physically counted by the cashier
            $table->decimal('difference', 10, 2)->nullable();      // closing_count - expected_cash
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        // Ties a completed cash sale to the shift it happened in, for reconciliation.
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('register_session_id')->nullable()->after('business_type')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('register_session_id');
        });
        Schema::dropIfExists('register_sessions');
    }
};
