<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per "client picks a lawyer" attempt - never mutated after it's
     * decided, so the full history (including every admin rejection and why)
     * is a free audit trail. cases.advocate_id is only ever set when a row
     * here is approved; until then the case is untouched and invisible to
     * the lawyer, same as before this feature existed.
     */
    public function up(): void
    {
        Schema::create('case_assignment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignUuid('lawyer_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignUuid('requested_by')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignUuid('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['case_id', 'status']);
            $table->index(['lawyer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_assignment_requests');
    }
};
