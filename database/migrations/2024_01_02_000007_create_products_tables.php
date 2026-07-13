<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model')->nullable();
            // product_type: simple|variable|bundle|service
            $table->string('product_type', 20)->default('simple');
            $table->string('condition', 20)->default('new'); // ProductCondition
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->longText('specifications')->nullable(); // free-form rich spec fallback

            // Money — always DECIMAL, never float.
            $table->decimal('price', 15, 2)->default(0);          // normal price
            $table->decimal('sale_price', 15, 2)->nullable();     // promo price
            $table->decimal('cost_price', 15, 2)->nullable();     // admin-only
            $table->string('price_status', 20)->default('fixed'); // fixed|call_for_price
            $table->boolean('price_includes_tax')->default(false);
            $table->boolean('is_taxable')->default(true);

            // Inventory (denormalised cache; source of truth is warehouse_stocks ledger)
            $table->integer('stock')->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->string('unit', 30)->default('pcs');

            // Shipping dimensions. Weight stored in grams; dimensions in centimetres.
            $table->unsignedInteger('weight_grams')->default(1000);
            $table->decimal('length_cm', 8, 2)->default(0);
            $table->decimal('width_cm', 8, 2)->default(0);
            $table->decimal('height_cm', 8, 2)->default(0);
            $table->unsignedSmallInteger('package_count')->default(1);
            $table->boolean('can_combine_package')->default(true);
            $table->boolean('requires_freight')->default(false); // kargo
            $table->boolean('pickup_only')->default(false);

            $table->string('warranty')->nullable();
            $table->string('estimated_processing')->nullable(); // e.g. "1-3 hari kerja"

            $table->string('main_image_path')->nullable();

            // Flags / merchandising
            $table->string('status', 20)->default('draft'); // draft|published|archived
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_promo')->default(false);
            $table->boolean('is_clearance')->default(false);
            // Purchase mode: directly buyable and/or quotation-only.
            $table->boolean('is_purchasable')->default(true);
            $table->boolean('requires_quotation')->default(false);
            $table->unsignedInteger('min_purchase')->default(1);
            $table->unsignedInteger('max_purchase')->nullable();

            // Denormalised rating + counters (kept in sync by services).
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('sold_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);

            // SEO
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('keywords', 500)->nullable();
            $table->string('canonical_url')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_purchasable']);
            $table->index(['category_id', 'status']);
            $table->index(['brand_id', 'status']);
            $table->index('price');
            $table->index('is_promo');
            $table->index('is_clearance');
        });

        // MySQL full-text index accelerates search; skipped on SQLite (tests use LIKE).
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('products', function (Blueprint $table) {
                $table->fullText(['name', 'short_description', 'description', 'keywords'], 'products_fulltext');
            });
        }

        Schema::create('product_slug_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('old_slug')->index();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('name'); // e.g. "550Wp / Hitam"
            $table->json('option_values')->nullable(); // {"Kapasitas":"550Wp","Warna":"Hitam"}
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->decimal('length_cm', 8, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('product_id');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('product_id');
        });

        Schema::create('product_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('datasheet'); // datasheet|manual|certificate
            $table->string('title');
            $table->string('path');
            $table->timestamps();
        });

        Schema::create('product_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('url'); // youtube/vimeo/self-hosted
            $table->timestamps();
        });

        // Dynamic per-product attribute values (section 8).
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value_text')->nullable();
            $table->decimal('value_number', 15, 4)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'attribute_id']);
            $table->index(['attribute_id', 'value_text']);
            $table->index(['attribute_id', 'value_number']);
        });

        // PLTS package bundle contents (section 9).
        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_replaceable')->default(false);
            $table->string('component_label')->nullable(); // e.g. "Inverter", "Baterai"
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bundle_items');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_videos');
        Schema::dropIfExists('product_documents');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_slug_histories');
        Schema::dropIfExists('products');
    }
};
