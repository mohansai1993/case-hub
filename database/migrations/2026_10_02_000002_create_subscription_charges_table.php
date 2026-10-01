<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only billing audit trail - every charge attempt, success or
     * failure. client_subscription_id is nullable: the very first charge on
     * "subscribe" happens before the subscription row exists yet.
     */
    public function up(): void
    {
        Schema::create('subscription_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_subscription_id')->nullable()->constrained('client_subscriptions')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->string('status', 20); // succeeded | failed
            $table->string('reason', 30); // subscribe | upgrade | downgrade | renewal
            $table->string('gateway_reference')->nullable();
            $table->string('failure_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['client_subscription_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_charges');
    }
};
