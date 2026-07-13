<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method'); // manual_transfer|virtual_account|qris|ewallet|card|term
            $table->string('provider')->nullable(); // adapter/gateway code
            $table->string('status', 30)->default('unpaid'); // PaymentStatus
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->boolean('is_down_payment')->default(false);
            $table->string('reference')->nullable();       // VA number / gateway ref
            $table->string('external_id')->nullable()->index(); // gateway transaction id
            $table->string('proof_path')->nullable();      // manual transfer proof upload
            $table->json('meta')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // charge|settlement|refund
            $table->decimal('amount', 15, 2);
            $table->string('status', 30);
            $table->string('gateway_reference')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        // Raw webhook log — signature-verified, replay-protected, idempotent processing.
        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event')->nullable();
            $table->string('external_id')->nullable()->index();
            // Unique event id / signature fingerprint to reject replays.
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->boolean('signature_valid')->default(false);
            $table->boolean('processed')->default(false);
            $table->longText('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payments');
    }
};
