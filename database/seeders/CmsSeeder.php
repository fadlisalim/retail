<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Banner;
use App\Models\Faq;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        // Banners
        Banner::updateOrCreate(['title' => 'Diskon Panel Surya Tier-1'], [
            'subtitle' => 'PROMO ENERGI SURYA', 'position' => 'hero', 'sort_order' => 1, 'is_active' => true,
            'description' => 'Hemat hingga 15% untuk panel surya monocrystalline pilihan.',
            'button_text' => 'Lihat Promo', 'button_url' => '/promo',
        ]);
        Banner::updateOrCreate(['title' => 'Paket PLTS Hybrid Siap Pasang'], [
            'subtitle' => 'SOLUSI LENGKAP', 'position' => 'hero', 'sort_order' => 2, 'is_active' => true,
            'description' => 'Panel, inverter, baterai, dan instalasi dalam satu paket hemat.',
            'button_text' => 'Belanja Paket', 'button_url' => '/kategori/paket-plts',
        ]);
        Banner::updateOrCreate(['title' => 'Pengadaan Proyek? Minta Penawaran'], [
            'subtitle' => 'UNTUK PERUSAHAAN & KONTRAKTOR', 'position' => 'quotation', 'sort_order' => 1, 'is_active' => true,
            'description' => 'Dapatkan harga khusus untuk pembelian skala proyek dengan BOQ.',
            'button_text' => 'Minta Penawaran', 'button_url' => '/permintaan-penawaran',
        ]);

        // Pages
        $pages = [
            ['tentang-kami', 'Tentang Rekasurya', 'PT Rekasurya Primadaya adalah penyedia produk energi terbarukan dan kebutuhan proyek PLTS di Indonesia.'],
            ['kontak', 'Kontak Kami', 'Hubungi kami di sales@rekasurya.test atau WhatsApp untuk konsultasi.'],
            ['syarat-ketentuan', 'Syarat & Ketentuan', 'Dengan berbelanja di Rekasurya Store, Anda menyetujui syarat dan ketentuan berikut.'],
            ['kebijakan-privasi', 'Kebijakan Privasi', 'Kami menjaga kerahasiaan data pelanggan sesuai peraturan yang berlaku.'],
            ['kebijakan-pengiriman', 'Kebijakan Pengiriman', 'Pengiriman menggunakan kurir reguler dan kargo berdasarkan berat aktual dan volumetrik.'],
            ['kebijakan-retur', 'Kebijakan Retur', 'Retur dapat diajukan maksimal 7 hari setelah barang diterima dengan syarat tertentu.'],
            ['informasi-garansi', 'Informasi Garansi', 'Setiap produk memiliki garansi sesuai brand dan kategori.'],
            ['panduan-belanja', 'Panduan Belanja', 'Cari produk, tambahkan ke keranjang, checkout, dan lakukan pembayaran.'],
            ['panduan-pembayaran', 'Panduan Pembayaran', 'Kami menerima transfer bank manual dan virtual account.'],
        ];
        foreach ($pages as [$slug, $title, $body]) {
            Page::updateOrCreate(['slug' => $slug], [
                'title' => $title,
                'content' => "<p>{$body}</p><p>Konten ini dapat diubah melalui panel admin.</p>",
                'is_published' => true,
                'meta_title' => "$title — Rekasurya Store",
                'meta_description' => Str::limit($body, 150),
            ]);
        }

        // Articles
        $author = User::where('is_staff', true)->first();
        $articles = [
            ['memilih-panel-surya-yang-tepat', 'Cara Memilih Panel Surya yang Tepat', 'Panduan memilih panel surya berdasarkan kebutuhan dan lokasi.'],
            ['plts-on-grid-vs-off-grid', 'PLTS On-Grid vs Off-Grid: Mana yang Cocok?', 'Perbedaan sistem on-grid dan off-grid untuk rumah Anda.'],
            ['merawat-baterai-lithium-plts', 'Tips Merawat Baterai Lithium PLTS', 'Cara memperpanjang umur baterai LiFePO4 pada sistem PLTS.'],
        ];
        foreach ($articles as $i => [$slug, $title, $excerpt]) {
            Article::updateOrCreate(['slug' => $slug], [
                'title' => $title,
                'excerpt' => $excerpt,
                'content' => "<p>{$excerpt}</p><p>Artikel lengkap akan membahas topik ini secara mendalam, termasuk rekomendasi produk dari Rekasurya Store.</p>",
                'author_id' => $author?->id,
                'is_published' => true,
                'published_at' => now()->subDays(($i + 1) * 3),
                'meta_title' => "$title — Rekasurya Store",
                'meta_description' => $excerpt,
            ]);
        }

        // FAQ
        $faqs = [
            ['Pemesanan', 'Bagaimana cara memesan produk?', 'Tambahkan produk ke keranjang lalu lanjutkan ke checkout.'],
            ['Pemesanan', 'Apakah bisa minta penawaran untuk proyek?', 'Ya, gunakan fitur Permintaan Penawaran dan unggah BOQ Anda.'],
            ['Pengiriman', 'Bagaimana ongkir dihitung?', 'Ongkir dihitung dari nilai terbesar antara berat aktual dan berat volumetrik.'],
            ['Pengiriman', 'Apakah bisa ambil di gudang?', 'Bisa, pilih opsi Ambil di Gudang saat checkout.'],
            ['Pembayaran', 'Metode pembayaran apa saja yang tersedia?', 'Transfer bank manual dan virtual account. Metode lain menyusul.'],
            ['Garansi', 'Bagaimana klaim garansi?', 'Hubungi customer service dengan menyertakan nomor pesanan.'],
        ];
        foreach ($faqs as $i => [$category, $q, $a]) {
            Faq::updateOrCreate(['question' => $q], [
                'category' => $category, 'answer' => $a, 'is_active' => true, 'sort_order' => $i,
            ]);
        }
    }
}
