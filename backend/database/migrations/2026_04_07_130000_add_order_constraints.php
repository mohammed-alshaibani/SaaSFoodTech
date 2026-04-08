<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            // Add unique constraint for pending orders per customer
            $table->unique(['customer_id', 'status'], 'unique_pending_order')
                ->where('status', 'pending');

            // Add business area field for geographic validation
            $table->string('business_area')->nullable()->after('longitude');

            // Add indexes for performance
            $table->index(['customer_id', 'status']);
            $table->index(['latitude', 'longitude']);
        });

        // Add check constraints for coordinate validation
        DB::statement('
            ALTER TABLE service_requests 
            ADD CONSTRAINT valid_coordinates 
            CHECK (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropUnique('unique_pending_order');
            $table->dropColumn('business_area');
            $table->dropIndex(['customer_id', 'status']);
            $table->dropIndex(['latitude', 'longitude']);
        });

        // Drop check constraint
        DB::statement('ALTER TABLE service_requests DROP CONSTRAINT valid_coordinates');
    }
};
