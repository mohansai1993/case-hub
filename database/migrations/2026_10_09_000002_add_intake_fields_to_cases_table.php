<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Full case-intake fields, and a draft/submitted split:
     *
     * A case now exists from the moment the client uploads their first piece
     * of evidence (so documents always have a case_id to attach to), but it
     * stays invisible to everyone - including the client's own case list -
     * until `submitted_at` is set by the "Create Case" step. advocate_id is
     * no longer picked by the client; an admin assigns one after reviewing
     * the submitted evidence, so it has to be nullable now.
     */
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->foreignUuid('advocate_id')->nullable()->change();
            $table->string('title', 150)->nullable()->change();

            $table->foreignId('practice_area_id')->nullable()->after('title')
                ->constrained('practice_areas')->restrictOnDelete();
            $table->text('description')->nullable()->after('practice_area_id');
            $table->date('incident_date')->nullable()->after('description');
            $table->string('location', 255)->nullable()->after('incident_date');
            $table->timestamp('submitted_at')->nullable()->after('status');

            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('practice_area_id');
            $table->dropColumn(['description', 'incident_date', 'location', 'submitted_at']);
            $table->foreignUuid('advocate_id')->nullable(false)->change();
            $table->string('title', 150)->nullable(false)->change();
        });
    }
};
