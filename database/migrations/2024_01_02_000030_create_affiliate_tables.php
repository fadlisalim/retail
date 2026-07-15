<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One affiliate account per user, holding KYC + payout details.
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('status', 20)->default('pending')->index();

            // Verification data (KYC)
            $table->string('full_name');
            $table->string('id_number', 40)->nullable();   // NIK / KTP
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->string('npwp', 40)->nullable();
            $table->string('channel')->nullable();          // website / sosmed / komunitas

            // Payout details
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number', 60)->nullable();
            $table->string('bank_account_holder')->nullable();

            $table->text('note')->nullable();               // internal admin note
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Lightweight click log for referral link stats.
        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('landing_url', 1024)->nullable();
            $table->string('referrer', 1024)->nullable();
            $table->timestamps();
            $table->index(['affiliate_id', 'created_at']);
        });

        // One commission line per attributed order item.
        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('base_amount', 14, 2);          // item line total (goods only)
            $table->decimal('rate', 5, 2);                  // percent applied
            $table->decimal('amount', 14, 2);               // commission earned
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
            $table->index(['affiliate_id', 'status']);
            // Guard against double-recording the same item.
            $table->unique(['order_id', 'order_item_id']);
        });

        // Withdrawal requests.
        Schema::create('affiliate_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('requested')->index();
            $table->string('method', 30)->default('bank_transfer');

            // Bank snapshot at request time (in case the affiliate later edits it).
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number', 60)->nullable();
            $table->string('bank_account_holder')->nullable();

            $table->string('reference')->nullable();        // transfer proof / ref no
            $table->text('note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_payouts');
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliates');
    }
};
