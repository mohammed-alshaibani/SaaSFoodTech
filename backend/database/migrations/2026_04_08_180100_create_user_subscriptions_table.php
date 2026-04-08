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
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->onDelete('restrict');
            $table->string('status')->default('active'); // active, canceled, expired, past_due
            $table->timestamp('starts_at')->default(now());
            $table->timestamp('ends_at')->nullable(); // For limited duration subscriptions
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('stripe_subscription_id')->nullable(); // For payment integration
            $table->string('stripe_customer_id')->nullable(); // For payment integration
            $table->json('metadata')->nullable(); // Additional subscription data
            $table->timestamps();
            
            $table->unique(['user_id', 'status']);
            $table->index(['status', 'ends_at']);
            $table->index(['stripe_subscription_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
