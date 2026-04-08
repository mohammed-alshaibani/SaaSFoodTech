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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->string('original_filename');
            $table->string('file_path');
            $table->enum('file_type', ['image', 'document']);
            $table->integer('file_size');
            $table->string('mime_type');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Indexes for performance
            $table->index(['service_request_id', 'file_type']);
            $table->index(['uploaded_by']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
