<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Landing-page copy for the "Paket PLTS" category (persuasive marketing intro
 * rendered above the product grid). Only fills the description when it's still
 * empty so edits made later via Admin → Kategori are never overwritten.
 */
class CategoryPaketPltsContentSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'paket-plts')->first();
        if (! $category) {
            $this->command?->warn('Kategori paket-plts tidak ditemukan — dilewati.');

            return;
        }

        $description = <<<'HTML'
<h2>☀️ Paket PLTS Lengkap — Hemat Tagihan PLN &amp; Bebas Panik Saat Mati Lampu</h2>
<p>Tagihan listrik naik terus? Sering mati lampu di momen penting? Saatnya atap rumah Kakak <strong>menghasilkan listrik sendiri</strong>. Semua paket PLTS di halaman ini sudah <strong>lengkap dan siap pakai</strong>: panel surya, inverter, baterai, proteksi, kabel, hingga mounting — tinggal pilih sesuai kebutuhan, tanpa pusing hitung komponen satu per satu.</p>

<h3>Kenapa pasang PLTS sekarang?</h3>
<ul>
<li>💸 <strong>Pangkas tagihan PLN hingga jutaan rupiah per tahun</strong> — matahari tidak pernah mengirim tagihan.</li>
<li>🔦 <strong>Anti mati lampu</strong> — kulkas, lampu, WiFi, dan perangkat penting tetap menyala saat listrik padam.</li>
<li>📈 <strong>Investasi jangka panjang</strong> — panel surya bergaransi performa hingga puluhan tahun, nilai properti pun ikut naik.</li>
<li>🌱 <strong>Energi bersih</strong> — kurangi jejak karbon keluarga tanpa mengubah gaya hidup.</li>
</ul>

<h3>Pilih paket sesuai kebutuhan</h3>
<ul>
<li>🏠 <strong>Rumah</strong> — mulai dari backup lampu &amp; elektronik penting sampai suplai rumah seharian.</li>
<li>🏢 <strong>Kantor &amp; Usaha</strong> — operasional tetap jalan, biaya listrik bulanan lebih ringan.</li>
<li>🏭 <strong>Industri / Proyek</strong> — skala besar dengan perhitungan teknis oleh tim engineer kami.</li>
<li>⚡ Tersedia sistem <strong>On-Grid</strong> (fokus hemat tagihan), <strong>Off-Grid</strong> (mandiri penuh), dan <strong>Hybrid</strong> (hemat + backup) — bingung bedanya? Tanya asisten kami di pojok kanan bawah 😊</li>
</ul>

<h3>Beli paket di Energi.Click itu gampang</h3>
<ul>
<li>✅ <strong>Semua sudah satu paket</strong> — komponen dijamin kompatibel, bergaransi resmi.</li>
<li>🛠️ <strong>Bisa sekalian instalasi</strong> oleh tim berpengalaman PT Rekasurya Primadaya (survei lokasi tersedia).</li>
<li>🤝 <strong>After-sales jelas</strong> — CS &amp; teknisi siap bantu setelah pemasangan, bukan ditinggal setelah bayar.</li>
</ul>

<p><strong>👇 Lihat pilihan paket di bawah ini.</strong> Butuh hitungan khusus untuk kebutuhan daya, atap, atau budget tertentu? <a href="/permintaan-penawaran">Minta penawaran gratis di sini</a> — tim kami balas cepat.</p>
HTML;

        if (blank($category->description)) {
            $category->description = $description;
        }

        // SEO meta — only when still the generic default / empty.
        if (blank($category->meta_description)) {
            $category->meta_description = 'Paket PLTS lengkap (panel surya + inverter + baterai + instalasi) untuk rumah, kantor, dan industri. On-grid, off-grid & hybrid. Hemat tagihan PLN, anti mati lampu. Garansi resmi Energi.Click by Rekasurya.';
        }

        $category->saveQuietly();

        $this->command?->info('Deskripsi landing kategori Paket PLTS terpasang (bisa diedit di Admin → Kategori).');
    }
}
