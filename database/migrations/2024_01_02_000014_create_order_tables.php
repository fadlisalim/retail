<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Human-facing order number (non-sequential, safe to show) + opaque public token.
            $table->string('order_number')->unique();
            $table->uuid('public_token')->unique(); // used in /pesanan/{token} tracking URL
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Guest checkout contact snapshot
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 30)->nullable();

            $table->string('status', 40)->default('draft');          // OrderStatus
            $table->string('payment_status', 30)->default('unpaid');  // PaymentStatus

            // Money breakdown — every figure recomputed server-side, never trusted from client.
            $table->decimal('items_subtotal', 15, 2)->default(0);
            $table->decimal('product_discount', 15, 2)->default(0);
            $table->decimal('coupon_discount', 15, 2)->default(0);
            $table->string('coupon_code')->nullable();
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('packing_fee', 15, 2)->default(0);
            $table->decimal('handling_fee', 15, 2)->default(0);
            $table->decimal('insurance_fee', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);

            $table->boolean('shipping_cost_confirmed')->default(true); // false = "ongkir dikonfirmasi"
            $table->string('shipping_method')->nullable();
            $table->string('shipping_service_name')->nullable();
            $table->unsignedInteger('billable_weight_grams')->default(0);

            $table->string('payment_method')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('internal_note')->nullable();

            // Idempotency key guards against double-submit creating duplicate orders.
            $table->string('idempotency_key', 64)->nullable()->unique();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('payment_status');
            $table->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            // Snapshots so the historical order is immutable even if the product changes.
            $table->string('sku');
            $table->string('name');
            $table->json('variant_options')->nullable();
            $table->decimal('unit_price', 15, 2);
            $table->decimal('original_unit_price', 15, 2)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->unsignedInteger('weight_grams')->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->timestamps();

            $table->index('order_id');
        });

        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('shipping'); // shipping|billing
            $table->string('recipient_name');
            $table->string('phone', 30);
            $table->string('company_name')->nullable();
            $table->string('npwp', 30)->nullable();
            $table->string('province');
            $table->string('city');
            $table->string('district')->nullable();
            $table->string('subdistrict')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->text('address_line');
            $table->string('landmark')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_note')->nullable();
            $table->text('customer_note')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('proof_path')->nullable();
            $table->timestamps();

            $table->index('order_id');
        });

        // Temporary stock holds created when a customer enters payment; auto-expire.
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 20)->default('active'); // active|released|committed|expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['product_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_addresses');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
