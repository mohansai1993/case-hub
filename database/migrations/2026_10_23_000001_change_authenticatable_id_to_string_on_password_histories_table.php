<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Both Admin (UUID) and User (string user_id) keys don't fit the
     * unsignedBigInteger this column was originally created with.
     */
    public function up(): void
    {
        Schema::table('password_histories', function (Blueprint $table) {
            $table->string('authenticatable_id')->change();
        });
    }

    public function down(): void
    {
        Schema::table('password_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('authenticatable_id')->change();
        });
    }
};
