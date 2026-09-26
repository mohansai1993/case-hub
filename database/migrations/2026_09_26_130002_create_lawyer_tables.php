<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalogue behind the "Practice areas / specialization" chips.
        Schema::create('practice_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lawyer_profiles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('location');
            $table->unsignedTinyInteger('years_of_experience');
            $table->text('bio')->nullable();
            // Set by an admin (dashboard "Lawyer Verification Request").
            $table->string('verification_status', 20)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lawyer_practice_area', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignId('practice_area_id')->constrained()->cascadeOnDelete();

            $table->primary(['user_id', 'practice_area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lawyer_practice_area');
        Schema::dropIfExists('lawyer_profiles');
        Schema::dropIfExists('practice_areas');
    }
};
