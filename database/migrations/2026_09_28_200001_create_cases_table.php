<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deliberately minimal: just enough for a client and lawyer to be paired
     * up so the chat feature has something to attach to. The full case
     * lifecycle (documents, description, location/date, status history) is a
     * separate feature.
     */
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('client_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignUuid('advocate_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();

            $table->index(['client_id']);
            $table->index(['advocate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
