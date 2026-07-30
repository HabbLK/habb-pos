<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('business_type')->index(); // retail | cafe | service
            $table->string('slug');                    // e.g. "drinks"
            $table->string('name');                     // e.g. "Drinks"
            $table->string('icon')->nullable();          // emoji used as the circular icon
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['business_type', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
