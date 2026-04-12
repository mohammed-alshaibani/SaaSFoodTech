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
        Schema::table('users', function (Blueprint $table) {
            // Index for authentication and user lookups
            $table->index(['email'], 'idx_users_email');
            $table->index(['plan'], 'idx_users_plan');
            $table->index(['created_at'], 'idx_users_created_at');

            // Composite indexes for common queries
            $table->index(['plan', 'created_at'], 'idx_users_plan_created');
            $table->index(['latitude', 'longitude'], 'idx_users_location');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            // Index for status-based queries
            $table->index(['status'], 'idx_requests_status');
            $table->index(['customer_id'], 'idx_requests_customer');
            $table->index(['provider_id'], 'idx_requests_provider');

            // Location-based indexes for nearby queries
            $table->index(['latitude', 'longitude'], 'idx_requests_location');

            // Time-based indexes for reporting
            $table->index(['created_at'], 'idx_requests_created_at');
            $table->index(['updated_at'], 'idx_requests_updated_at');
            $table->index(['accepted_at'], 'idx_requests_accepted_at');
            $table->index(['completed_at'], 'idx_requests_completed_at');

            // Composite indexes for common query patterns
            $table->index(['status', 'created_at'], 'idx_requests_status_created');
            $table->index(['customer_id', 'status'], 'idx_requests_customer_status');
            $table->index(['provider_id', 'status'], 'idx_requests_provider_status');
            $table->index(['status', 'latitude', 'longitude'], 'idx_requests_status_location');

            // Full-text search index for title and description
            $table->index(['title'], 'idx_requests_title');
        });

        // Use raw SQL for TEXT column index prefix in MySQL
        if (config('database.default') === 'mysql') {
            DB::statement('CREATE INDEX idx_requests_description ON service_requests (description(191))');
        } else {
            Schema::table('service_requests', function (Blueprint $table) {
                $table->index(['description'], 'idx_requests_description');
            });
        }

        Schema::table('permissions', function (Blueprint $table) {
            // Indexes for RBAC queries
            $table->index(['name'], 'idx_permissions_name');
            $table->index(['category_id'], 'idx_permissions_category');
            $table->index(['group'], 'idx_permissions_group');
            $table->index(['is_system'], 'idx_permissions_system');

            // Composite indexes
            $table->index(['category_id', 'group'], 'idx_permissions_category_group');
            $table->index(['is_system', 'category_id'], 'idx_permissions_system_category');
        });

        Schema::table('roles', function (Blueprint $table) {
            // Indexes for role queries
            $table->index(['name'], 'idx_roles_name');
            $table->index(['guard_name'], 'idx_roles_guard');
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            // Indexes for user permission lookups
            $table->index(['permission_id'], 'idx_model_has_permissions_permission');
            $table->index(['model_type', 'model_id'], 'idx_model_has_permissions_model');

            // Composite index for user permission checks
            $table->index(['model_type', 'model_id', 'permission_id'], 'idx_model_has_permissions_composite');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            // Indexes for user role lookups
            $table->index(['role_id'], 'idx_model_has_roles_role');
            $table->index(['model_type', 'model_id'], 'idx_model_has_roles_model');

            // Composite index for user role checks
            $table->index(['model_type', 'model_id', 'role_id'], 'idx_model_has_roles_composite');
        });

        Schema::table('role_has_permissions', function (Blueprint $table) {
            // Indexes for role permission queries
            $table->index(['role_id'], 'idx_role_has_permissions_role');
            $table->index(['permission_id'], 'idx_role_has_permissions_permission');

            // Composite index for role permission checks
            $table->index(['role_id', 'permission_id'], 'idx_role_has_permissions_composite');
        });

        Schema::table('role_hierarchy', function (Blueprint $table) {
            // Indexes for hierarchy queries
            $table->index(['parent_role_id'], 'idx_role_hierarchy_parent');
            $table->index(['child_role_id'], 'idx_role_hierarchy_child');

            // Unique index already exists, but ensure it's optimized
            $table->unique(['parent_role_id', 'child_role_id'], 'uk_role_hierarchy_parent_child');
        });

        Schema::table('user_permissions', function (Blueprint $table) {
            // Indexes for direct user permissions
            $table->index(['user_id'], 'idx_user_permissions_user');
            $table->index(['permission_id'], 'idx_user_permissions_permission');
            $table->index(['type'], 'idx_user_permissions_type');
            $table->index(['expires_at'], 'idx_user_permissions_expires');

            // Composite indexes for common queries
            $table->index(['user_id', 'permission_id'], 'idx_user_permissions_composite');
            $table->index(['user_id', 'type', 'expires_at'], 'idx_user_permissions_active');
        });

        Schema::table('role_permissions_audit', function (Blueprint $table) {
            // Indexes for audit queries
            $table->index(['role_id'], 'idx_audit_role');
            $table->index(['permission_id'], 'idx_audit_permission');
            $table->index(['performed_by'], 'idx_audit_performer');
            $table->index(['action'], 'idx_audit_action');
            $table->index(['created_at'], 'idx_audit_created');

            // Composite indexes for reporting
            $table->index(['role_id', 'action'], 'idx_audit_role_action');
            $table->index(['performed_by', 'created_at'], 'idx_audit_performer_time');
            $table->index(['action', 'created_at'], 'idx_audit_action_time');
        });

        Schema::table('permission_scopes', function (Blueprint $table) {
            // Indexes for scoped permission queries
            $table->index(['permission_id'], 'idx_scopes_permission');
            $table->index(['scope_type'], 'idx_scopes_type');

            // Composite index
            $table->index(['permission_id', 'scope_type'], 'idx_scopes_composite');
        });

        Schema::table('attachments', function (Blueprint $table) {
            // Indexes for file queries
            $table->index(['service_request_id'], 'idx_attachments_request');
            $table->index(['uploaded_by'], 'idx_attachments_uploader');
            $table->index(['file_type'], 'idx_attachments_type');
            $table->index(['created_at'], 'idx_attachments_created');

            // Composite indexes
            $table->index(['service_request_id', 'file_type'], 'idx_attachments_request_type');
        });

        // Create spatial index for location-based queries (MySQL specific)
        // Disabled: Spatial indexes require a GEOMETRY column, but latitude/longitude are DECIMAL.
        // if (config('database.default') === 'mysql') {
        //     DB::statement('ALTER TABLE service_requests ADD SPATIAL INDEX idx_requests_spatial (POINT(longitude, latitude))');
        // }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_email');
            $table->dropIndex('idx_users_plan');
            $table->dropIndex('idx_users_created_at');
            $table->dropIndex('idx_users_plan_created');
            $table->dropIndex('idx_users_location');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropIndex('idx_requests_status');
            $table->dropIndex('idx_requests_customer');
            $table->dropIndex('idx_requests_provider');
            $table->dropIndex('idx_requests_location');
            $table->dropIndex('idx_requests_created_at');
            $table->dropIndex('idx_requests_updated_at');
            $table->dropIndex('idx_requests_accepted_at');
            $table->dropIndex('idx_requests_completed_at');
            $table->dropIndex('idx_requests_status_created');
            $table->dropIndex('idx_requests_customer_status');
            $table->dropIndex('idx_requests_provider_status');
            $table->dropIndex('idx_requests_status_location');
            $table->dropIndex('idx_requests_title');
            $table->dropIndex('idx_requests_description');

            // Drop spatial index if it exists (MySQL specific)
            if (config('database.default') === 'mysql') {
                try {
                    DB::statement('ALTER TABLE service_requests DROP INDEX idx_requests_spatial');
                } catch (\Exception $e) {
                    // Ignore if not exists
                }
            }
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex('idx_permissions_name');
            $table->dropIndex('idx_permissions_category');
            $table->dropIndex('idx_permissions_group');
            $table->dropIndex('idx_permissions_system');
            $table->dropIndex('idx_permissions_category_group');
            $table->dropIndex('idx_permissions_system_category');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex('idx_roles_name');
            $table->dropIndex('idx_roles_guard');
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropIndex('idx_model_has_permissions_permission');
            $table->dropIndex('idx_model_has_permissions_model');
            $table->dropIndex('idx_model_has_permissions_composite');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropIndex('idx_model_has_roles_role');
            $table->dropIndex('idx_model_has_roles_model');
            $table->dropIndex('idx_model_has_roles_composite');
        });

        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->dropIndex('idx_role_has_permissions_role');
            $table->dropIndex('idx_role_has_permissions_permission');
            $table->dropIndex('idx_role_has_permissions_composite');
        });

        Schema::table('role_hierarchy', function (Blueprint $table) {
            $table->dropIndex('idx_role_hierarchy_parent');
            $table->dropIndex('idx_role_hierarchy_child');
            $table->dropUnique('uk_role_hierarchy_parent_child');
        });

        Schema::table('user_permissions', function (Blueprint $table) {
            $table->dropIndex('idx_user_permissions_user');
            $table->dropIndex('idx_user_permissions_permission');
            $table->dropIndex('idx_user_permissions_type');
            $table->dropIndex('idx_user_permissions_expires');
            $table->dropIndex('idx_user_permissions_composite');
            $table->dropIndex('idx_user_permissions_active');
        });

        Schema::table('role_permissions_audit', function (Blueprint $table) {
            $table->dropIndex('idx_audit_role');
            $table->dropIndex('idx_audit_permission');
            $table->dropIndex('idx_audit_performer');
            $table->dropIndex('idx_audit_action');
            $table->dropIndex('idx_audit_created');
            $table->dropIndex('idx_audit_role_action');
            $table->dropIndex('idx_audit_performer_time');
            $table->dropIndex('idx_audit_action_time');
        });

        Schema::table('permission_scopes', function (Blueprint $table) {
            $table->dropIndex('idx_scopes_permission');
            $table->dropIndex('idx_scopes_type');
            $table->dropIndex('idx_scopes_composite');
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex('idx_attachments_request');
            $table->dropIndex('idx_attachments_uploader');
            $table->dropIndex('idx_attachments_type');
            $table->dropIndex('idx_attachments_created');
            $table->dropIndex('idx_attachments_request_type');
        });
    }
};
