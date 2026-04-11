<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Skip if table doesn't exist yet (will be created by later migration)
        if (!Schema::hasTable('user_subscriptions')) {
            return;
        }
        
        Schema::table('user_subscriptions', function (Blueprint $table) {
            // First, create a regular index on user_id so the foreign key stays happy
            $table->index('user_id', 'user_id_fk_index');
            
            // Now we can safely drop the unique one
            $table->dropUnique(['user_id', 'status']);
            
            // Add a proper composite index for performance
            $table->index(['user_id', 'status'], 'user_subscriptions_user_id_status_index');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_subscriptions')) {
            return;
        }
        
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->unique(['user_id', 'status'], 'user_subscriptions_user_id_status_unique');
            $table->dropIndex('user_subscriptions_user_id_status_index');
            $table->dropIndex('user_id_fk_index');
        });
    }
};
