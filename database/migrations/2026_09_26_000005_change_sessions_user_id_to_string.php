<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The framework's sessions table uses a bigint user_id, but this app's
     * authenticatable models use UUID keys, which the database session driver
     * would fail to store.
     */
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->string('user_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intentionally not reverted: UUIDs cannot be cast back to bigint.
    }
};
