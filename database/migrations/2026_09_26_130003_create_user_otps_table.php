<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One live code per (user, purpose); requesting a new one replaces it.
        // Only keyed hashes are stored, never the code or the reset token.
        Schema::create('user_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('purpose', 30);
            $table->string('code_hash', 64);
            $table->dateTime('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('last_sent_at');
            $table->dateTime('verified_at')->nullable();
            $table->string('token_hash', 64)->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_otps');
    }
};
