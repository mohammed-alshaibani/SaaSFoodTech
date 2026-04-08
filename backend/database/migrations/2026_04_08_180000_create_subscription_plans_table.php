<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // free, basic, premium, enterprise
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2)->default(0);
            $table->string('billing_cycle')->default('monthly'); // monthly, yearly
            $table->json('features'); // JSON array of features
            $table->json('limits'); // JSON object with limits
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('stripe_price_id')->nullable(); // For payment integration
            $table->timestamps();
            
            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
