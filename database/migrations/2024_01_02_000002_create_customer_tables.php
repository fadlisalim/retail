<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Indonesian administrative regions, self-referencing tree
        // (province -> city/regency -> district -> subdistrict). Manageable by admin.
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('code', 20)->nullable()->index();
            $table->string('name');
            $table->string('type', 20); // province|city|district|subdistrict
            $table->string('postal_code', 10)->nullable();
            $table->timestamps();

            $table->index(['type', 'parent_id']);
        });

        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->string('npwp', 30)->nullable();
            $table->string('customer_type', 20)->default('personal'); // personal|business
            // Term/tempo payment eligibility is approved by admin, never self-service.
            $table->boolean('term_payment_approved')->default(false);
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->string('referral_source')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 30)->default('Rumah'); // Rumah|Kantor|Gudang|Proyek
            $table->string('recipient_name');
            $table->string('phone', 30);
            $table->string('company_name')->nullable();
            $table->string('npwp', 30)->nullable();
            $table->string('province');
            $table->string('city');
            $table->string('district')->nullable();
            $table->string('subdistrict')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->text('address_line');
            $table->string('landmark')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('regions');
    }
};
