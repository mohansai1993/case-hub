<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per client (unique client_id) - a client never holds more than
     * one active plan at a time; upgrading/downgrading replaces this same
     * row rather than creating a new one. Charge history lives separately in
     * subscription_charges.
     */
    public function up(): void
    {
        Schema::create('client_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('client_id')->unique()->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('status', 20)->default('active')->index();

            // Gateway-agnostic references (Paystack/Flutterwave/etc - whichever is wired later).
            $table->string('gateway_customer_id')->nullable();
            $table->string('gateway_subscription_id')->nullable();

            $table->timestamp('current_period_ends_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('restricted_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_subscriptions');
    }
};
