<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit log: one row per "Send Notification" click, snapshotting
     * who it went to at the time (names can drift after, e.g. a client renaming
     * their account, without rewriting history).
     */
    public function up(): void
    {
        Schema::create('notification_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_draft_id')->nullable()->constrained('notification_drafts')->nullOnDelete();
            $table->string('title', 150);
            $table->text('message');
            $table->string('audience', 10); // client | lawyer
            $table->boolean('is_bulk')->default(false);
            $table->unsignedInteger('recipient_count')->default(0);
            $table->text('recipient_names')->nullable();
            $table->foreignUuid('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_broadcasts');
    }
};
