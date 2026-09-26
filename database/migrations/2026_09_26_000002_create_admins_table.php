<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('name');
            // Stored lowercase; mobile stored as 10 digits (see App\Support\Identifier).
            $table->string('email')->unique();
            $table->string('mobile', 15)->nullable()->unique();
            $table->string('password');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
