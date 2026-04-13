<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add Arabic localisation columns to subscription_plans.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Arabic name (stored separately from display_name which was used before)
            $table->string('name_ar')->nullable()->after('display_name');
            // Arabic description
            $table->text('description_ar')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'description_ar']);
        });
    }
};
