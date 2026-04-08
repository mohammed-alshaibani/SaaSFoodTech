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
        // Create role_hierarchy table for role relationships
        Schema::create('role_hierarchy', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_role_id');
            $table->unsignedBigInteger('child_role_id');
            $table->timestamps();

            $table->foreign('parent_role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('child_role_id')->references('id')->on('roles')->onDelete('cascade');
            
            $table->unique(['parent_role_id', 'child_role_id']);
        });

        // Create permission_categories table for organizing permissions
        Schema::create('permission_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Add category_id to permissions table
        Schema::table('permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('id');
            $table->text('description')->nullable()->after('name');
            $table->string('group')->nullable()->after('description');
            $table->boolean('is_system')->default(false)->after('group');
            
            $table->foreign('category_id')->references('id')->on('permission_categories')->onDelete('set null');
            $table->index(['category_id', 'group']);
        });

        // Create user_permissions table for direct user permissions (bypass roles)
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('permission_id');
            $table->enum('type', ['grant', 'deny'])->default('grant');
            $table->unsignedBigInteger('granted_by')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->foreign('granted_by')->references('id')->on('users')->onDelete('set null');
            
            $table->unique(['user_id', 'permission_id']);
            $table->index(['user_id', 'type']);
        });

        // Create role_permissions_audit table for tracking permission changes
        Schema::create('role_permissions_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->enum('action', ['granted', 'revoked']);
            $table->unsignedBigInteger('performed_by');
            $table->text('reason')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('cascade');
            
            $table->index(['role_id', 'action']);
            $table->index(['performed_by', 'created_at']);
        });

        // Create permission_scopes table for scoped permissions
        Schema::create('permission_scopes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('permission_id');
            $table->string('scope_type'); // e.g., 'department', 'location', 'team'
            $table->json('scope_values'); // e.g., [1, 2, 3] or ['NY', 'LA']
            $table->timestamps();

            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->index(['permission_id', 'scope_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_scopes');
        Schema::dropIfExists('role_permissions_audit');
        Schema::dropIfExists('user_permissions');
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id', 'group']);
            $table->dropColumn(['category_id', 'description', 'group', 'is_system']);
        });
        Schema::dropIfExists('permission_categories');
        Schema::dropIfExists('role_hierarchy');
    }
};
