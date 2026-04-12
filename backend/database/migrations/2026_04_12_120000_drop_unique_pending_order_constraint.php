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
        // Skip index drop logic for SQLite as the partial index may not exist or requires table recreation
        if (config('database.default') === 'sqlite') {
            return;
        }

        Schema::table('service_requests', function (Blueprint $table) {
            try {
                $table->dropUnique('unique_pending_order');
            } catch (\Exception $e) {
                // Ignore if not exists
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->unique(['customer_id', 'status'], 'unique_pending_order');
        });
    }
};
