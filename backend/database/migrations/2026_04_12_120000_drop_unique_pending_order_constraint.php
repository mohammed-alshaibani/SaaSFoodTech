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
        Schema::table('service_requests', function (Blueprint $table) {
            // Drop the constraint preventing users from having multiple pending requests
            // We use DROP INDEX generically in case it was created as a distinct index.
            // On sqlite dropUnique requires a column array, but dropping the explicitly named index handles overrides seamlessly.
            try {
                $table->dropUnique('unique_pending_order');
            } catch (\Exception $e) {
                try {
                    $table->dropIndex('unique_pending_order');
                } catch (\Exception $e2) {
                    // ignore if already dropped
                }
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
