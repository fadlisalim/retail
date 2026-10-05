<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp Campaign (promo via Wablas) — kontak + izin promo, template,
 * campaign, antrean pesan persisten per penerima, dan log operasional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();                 // 62xxxxxxxxxx (dinormalisasi)
            $table->string('name', 150)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30)->default('manual');       // customer, order, lead, wachat, import, manual, checkout, register
            $table->json('tags')->nullable();                      // ["event-sept", "reseller"]
            $table->json('interests')->nullable();                 // slug kategori / produk yang pernah dibeli / ditanyakan
            $table->string('consent_status', 12)->default('unknown')->index(); // unknown | opted_in | opted_out
            $table->string('consent_source', 60)->nullable();      // checkout, register, import, manual, chat
            $table->text('consent_proof')->nullable();             // bukti izin (teks: form/event/tanggal/IP hash)
            $table->timestamp('consent_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->string('opted_out_reason', 120)->nullable();   // "Balas STOP", "admin", "import"
            $table->timestamp('last_promo_at')->nullable()->index(); // batas 1 promo / 7 hari lintas campaign
            $table->unsignedInteger('orders_count')->default(0);
            $table->timestamp('last_order_at')->nullable();
            $table->decimal('total_spent', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_campaign_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('body');                                  // boleh memakai {nama}, {link}
            $table->string('image_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('wa_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('status', 12)->default('draft')->index(); // draft, scheduled, running, paused, completed, cancelled
            $table->text('message');                               // isi pesan dengan {nama} / {link}
            $table->string('image_path')->nullable();              // upload atau foto produk katalog
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('link_url', 500)->nullable();           // link produk/halaman (UTM ditambah saat render)
            $table->string('utm_campaign', 100)->nullable();
            $table->json('segment')->nullable();                   // filter audiens
            $table->unsignedInteger('audience_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('pause_reason', 200)->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('wa_campaign_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('wa_campaigns')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->string('phone', 20)->index();
            // queued → sending → accepted (diterima gateway) → sent → delivered → read
            // lainnya: failed, uncertain (timeout, hasil tak diketahui), skipped, cancelled
            $table->string('status', 12)->default('queued')->index();
            $table->text('rendered_message')->nullable();
            $table->string('wablas_id', 100)->nullable()->index();  // id pesan dari Wablas (status webhook / cancel)
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedSmallInteger('reply_count')->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'contact_id']);        // satu pesan per penerima per campaign
            $table->index(['campaign_id', 'status']);
        });

        Schema::create('wa_campaign_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained('wa_campaigns')->cascadeOnDelete();
            $table->string('level', 10)->default('info');          // info, warning, error
            $table->string('message', 500);
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_campaign_logs');
        Schema::dropIfExists('wa_campaign_messages');
        Schema::dropIfExists('wa_campaigns');
        Schema::dropIfExists('wa_campaign_templates');
        Schema::dropIfExists('wa_contacts');
    }
};
