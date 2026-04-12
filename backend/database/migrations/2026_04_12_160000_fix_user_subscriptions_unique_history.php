<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            // Add a regular index on user_id first to satisfy the FK requirement
            // so we can safely drop the composite unique index
            $table->index('user_id', 'idx_user_subscriptions_user_id');

            // Drop the restrictive unique index
            try {
                $table->dropUnique('user_subscriptions_user_id_status_unique');
            } catch (\Exception $e) {
            }

            // Re-add a composite index for performance
            $table->index(['user_id', 'status'], 'idx_user_subscription_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_user_subscription_status');
            $table->unique(['user_id', 'status']);
        });
    }
};
