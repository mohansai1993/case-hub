<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evidence files attached to a case. Count toward the case's CLIENT
     * storage quota regardless of who uploaded them (the client's vault
     * holds everything related to their case).
     *
     * inaccessible_at: set when a downgrade pushes usage over the new,
     * smaller limit (oldest files first) - never hard-deleted automatically,
     * just hidden/blocked, so a mistake here is always recoverable.
     */
    public function up(): void
    {
        Schema::create('case_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamp('inaccessible_at')->nullable();
            $table->timestamps();

            $table->index(['case_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_documents');
    }
};
