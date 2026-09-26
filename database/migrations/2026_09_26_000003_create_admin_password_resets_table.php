<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // At most one live reset attempt per admin (unique admin_id): asking for
        // a new OTP replaces the previous one. Only hashes are stored.
        Schema::create('admin_password_resets', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('admin_id')->unique()->constrained('admins')->cascadeOnDelete();
            $table->string('otp_hash', 64);
            $table->dateTime('otp_expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('last_sent_at');
            $table->dateTime('verified_at')->nullable();
            $table->string('reset_token_hash', 64)->nullable();
            $table->dateTime('reset_token_expires_at')->nullable();
            $table->string('requested_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_password_resets');
    }
};
