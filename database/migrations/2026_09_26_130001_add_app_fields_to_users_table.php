<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `users` holds the app-side accounts (clients and lawyers). The legacy
     * `role` column is left untouched; `type` is the client/lawyer split.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('type', 20)->default('client')->after('user_id')->index();
            $table->string('status', 20)->default('active')->after('type')->index();
            $table->timestamp('mobile_verified_at')->nullable()->after('mobile');
            $table->timestamp('terms_accepted_at')->nullable();
            $table->unique('mobile');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['mobile']);
            $table->dropColumn(['type', 'status', 'mobile_verified_at', 'terms_accepted_at']);
        });
    }
};
