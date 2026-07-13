<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->string('type', 20)->default('percent'); // percent|fixed|free_shipping
            $table->decimal('value', 15, 2)->default(0);
            $table->decimal('min_subtotal', 15, 2)->default(0);
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();  // global quota
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // customer-specific
            $table->boolean('is_combinable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['coupon_id', 'user_id']);
        });

        // Automatic promotions (product/category/brand/flash-sale) applied without a code.
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 30)->default('product'); // product|category|brand|cart|flash_sale
            $table->string('discount_type', 20)->default('percent'); // percent|fixed
            $table->decimal('value', 15, 2)->default(0);
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->json('targets')->nullable(); // ids of products/categories/brands
            $table->decimal('min_qty', 15, 2)->nullable();
            $table->boolean('is_combinable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
    }
};
