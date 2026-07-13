<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('rfq_number')->unique();      // request number, always present
            $table->string('quotation_number')->nullable()->unique(); // assigned when quoted
            $table->uuid('public_token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('new'); // QuotationStatus

            // Requester + project details
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone', 30)->nullable();
            $table->string('company_name')->nullable();
            $table->string('npwp', 30)->nullable();
            $table->string('project_name')->nullable();
            $table->string('project_location')->nullable();
            $table->date('procurement_target')->nullable();
            $table->boolean('needs_installation')->default(false);
            $table->text('technical_notes')->nullable();

            // Priced by admin
            $table->decimal('items_subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('payment_terms')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('admin_note')->nullable();

            $table->unsignedBigInteger('converted_order_id')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('note')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0); // 0 until admin prices it
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->timestamps();
        });

        Schema::create('quotation_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('snapshot'); // full priced snapshot for the revision history
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quotation_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('customer'); // customer (BOQ) | admin (offer)
            $table->string('title');
            $table->string('path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_attachments');
        Schema::dropIfExists('quotation_revisions');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
