<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A cart belongs to a user OR an anonymous session token (guest cart),
        // which lets us merge the guest cart into the account on login.
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_token', 64)->nullable()->index();
            $table->string('coupon_code')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            // Snapshot of unit price when added; always recomputed server-side at checkout.
            $table->decimal('unit_price_snapshot', 15, 2)->default(0);
            $table->boolean('saved_for_later')->default(false);
            // Acknowledgement for open-box/used items, captured before checkout.
            $table->boolean('condition_acknowledged')->default(false);
            $table->timestamps();

            $table->index('cart_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
