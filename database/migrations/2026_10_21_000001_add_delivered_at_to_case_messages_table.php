<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Splits "the other party has it" (delivered - their device received it,
     * chat screen open or not) from "they actually opened and read it"
     * (read_at, already existed) - the WhatsApp-style grey-tick/blue-tick
     * distinction.
     */
    public function up(): void
    {
        Schema::table('case_messages', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('sender_id');
            $table->index('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('case_messages', function (Blueprint $table) {
            $table->dropColumn('delivered_at');
        });
    }
};
