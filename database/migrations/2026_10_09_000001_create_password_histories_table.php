<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every password an account (Admin or User) has ever had, oldest first.
     * Checked on every password change so the last N passwords cannot be
     * reused - the live password on the account itself counts as the most
     * recent entry, this table only holds the ones it has since replaced.
     */
    public function up(): void
    {
        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->string('authenticatable_type');
            $table->unsignedBigInteger('authenticatable_id');
            $table->string('password_hash');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['authenticatable_type', 'authenticatable_id', 'id'], 'password_histories_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_histories');
    }
};
