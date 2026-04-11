<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            // Drop the restrictive unique constraint to allow multiple pending orders
            $table->dropUnique('unique_pending_order');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->unique(['customer_id', 'status'], 'unique_pending_order')
                ->where('status', 'pending');
        });
    }
};
