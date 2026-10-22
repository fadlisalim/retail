<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Halaman kebijakan toko (Tentang, Kontak, S&K, Privasi, Pengiriman, Retur, Garansi,
 * Panduan Belanja & Pembayaran) — isi mengikuti praktik umum marketplace Indonesia
 * dan alur yang benar-benar berjalan di sistem (transfer bank/QRIS manual, batas bayar
 * otomatis, kargo untuk barang berat, ambil di gudang, retur lewat CS).
 *
 * Penanda {brand}, {perusahaan}, {alamat}, {email}, {telepon}, {whatsapp},
 * {jam_operasional}, {batas_bayar} diisi otomatis dari Pengaturan saat ditampilkan.
 * Halaman yang SUDAH disunting admin (bukan teks template awal) tidak ditimpa.
 */
class KebijakanTokoSeeder extends Seeder
{
    public function run(): void
    {
        $written = 0;
        foreach ($this->pages() as $slug => [$title, $meta, $content]) {
            $page = Page::where('slug', $slug)->first();
            if ($page && ! $this->isTemplate((string) $page->content)) {
                continue;
            }
            Page::updateOrCreate(['slug' => $slug], [
                'title' => $title,
                'content' => trim($content),
                'is_published' => true,
                'meta_title' => $title,
                'meta_description' => Str::limit($meta, 155),
            ]);
            $written++;
        }

        $this->command?->info("Halaman kebijakan: {$written} ditulis (halaman yang sudah disunting admin dilewati).");
    }

    /** Teks bawaan instalasi awal: pendek & berakhiran catatan "dapat diubah melalui panel admin". */
    private function isTemplate(string $content): bool
    {
        return str_contains($content, 'Konten ini dapat diubah melalui panel admin') || mb_strlen(strip_tags($content)) < 200;
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    private function pages(): array
    {
        $kontak = '<p>Butuh bantuan? Hubungi CS {brand} via WhatsApp {whatsapp} atau email {email} ({jam_operasional}).</p>';

        return [
            'tentang-kami' => ['Tentang Kami', 'Energi.Click by Rekasurya — toko online panel surya, inverter, baterai LiFePO4, paket PLTS & PJU tenaga surya untuk rumah, usaha dan proyek.', <<<'HTML'
<p><strong>{brand}</strong> adalah toko online perlengkapan energi surya yang dikelola oleh <strong>{perusahaan}</strong>. Kami menyediakan panel surya, inverter, baterai lithium LiFePO4, paket PLTS, PJU tenaga surya, pompa air tenaga surya, kabel & proteksi, hingga barang clearance sisa proyek dengan harga terbaik.</p>
<h2>Yang kami layani</h2>
<ul>
<li><strong>Pembelian satuan (retail)</strong> — rumah tangga, toko, bengkel, dan teknisi.</li>
<li><strong>Kebutuhan proyek</strong> — kontraktor, perusahaan, instansi, dan pengadaan skala besar lewat <a href="/permintaan-penawaran">Permintaan Penawaran</a>.</li>
<li><strong>Konsultasi teknis</strong> — hitung kebutuhan daya, pilih sistem (on-grid, hybrid, off-grid), hingga survei & pemasangan.</li>
</ul>
<h2>Kenapa belanja di {brand}</h2>
<ul>
<li>Produk bergaransi resmi sesuai ketentuan masing-masing produsen.</li>
<li>Barang dicek & didokumentasikan sebelum dikirim — fotonya bisa dilihat di halaman lacak pesanan.</li>
<li>Harga jelas: harga produk belum termasuk PPN, PPN ditampilkan terpisah saat checkout.</li>
<li>Pengiriman ke seluruh Indonesia lewat kurir maupun kargo untuk barang besar.</li>
</ul>
<h2>Alamat</h2>
<p>{perusahaan}<br>{alamat}<br>Telepon {telepon} · WhatsApp {whatsapp} · {email}</p>
HTML],

            'kontak' => ['Kontak Kami', 'Hubungi Energi.Click: WhatsApp, email, telepon dan alamat kantor/gudang.', <<<'HTML'
<ul>
<li><strong>WhatsApp CS:</strong> {whatsapp}</li>
<li><strong>Email:</strong> {email}</li>
<li><strong>Telepon:</strong> {telepon}</li>
<li><strong>Jam operasional:</strong> {jam_operasional}</li>
<li><strong>Alamat:</strong> {perusahaan}, {alamat}</li>
</ul>
<p>Untuk pertanyaan pesanan, sertakan <strong>nomor pesanan</strong> agar kami bisa membantu lebih cepat. Untuk kebutuhan proyek, gunakan <a href="/permintaan-penawaran">Permintaan Penawaran</a>.</p>
HTML],

            'syarat-ketentuan' => ['Syarat & Ketentuan', 'Syarat dan ketentuan berbelanja di Energi.Click: akun, harga, pesanan, pembayaran, pengiriman, retur dan garansi.', <<<'HTML'
<p>Dengan mengakses dan berbelanja di {brand} (dikelola oleh {perusahaan}), Anda menyetujui syarat & ketentuan berikut.</p>
<h2>1. Akun</h2>
<ul>
<li>Data yang Anda isi (nama, nomor WhatsApp, email, alamat) harus benar dan dapat dihubungi.</li>
<li>Anda bertanggung jawab menjaga kerahasiaan kata sandi dan seluruh aktivitas pada akun Anda.</li>
</ul>
<h2>2. Harga & stok</h2>
<ul>
<li>Harga produk dalam Rupiah dan <strong>belum termasuk PPN</strong>; PPN (bila berlaku) dan ongkos kirim ditampilkan saat checkout.</li>
<li>Harga, promo, dan stok dapat berubah sewaktu-waktu. Harga yang berlaku adalah harga saat pesanan dibuat.</li>
<li>Foto produk dapat sedikit berbeda dengan barang asli (warna, kemasan) tanpa mengubah spesifikasi.</li>
</ul>
<h2>3. Pesanan</h2>
<ul>
<li>Pesanan diproses setelah pembayaran terverifikasi.</li>
<li>Kami berhak membatalkan pesanan bila stok habis, terjadi kesalahan harga/sistem, atau terindikasi penyalahgunaan; dana yang sudah dibayar dikembalikan penuh.</li>
<li>Pesanan yang belum dibayar dalam {batas_bayar} dibatalkan otomatis.</li>
</ul>
<h2>4. Pembayaran, pengiriman, retur & garansi</h2>
<p>Mengikuti <a href="/halaman/panduan-pembayaran">Panduan Pembayaran</a>, <a href="/halaman/kebijakan-pengiriman">Kebijakan Pengiriman</a>, <a href="/halaman/kebijakan-retur">Kebijakan Retur</a>, dan <a href="/halaman/informasi-garansi">Informasi Garansi</a>.</p>
<h2>5. Produk clearance / bekas proyek</h2>
<p>Produk berlabel clearance, bekas proyek, atau kondisi selain baru dijual sesuai kondisi yang dijelaskan di halaman produk. Retur hanya berlaku bila barang tidak sesuai deskripsi.</p>
<h2>6. Penawaran proyek</h2>
<p>Penawaran harga (quotation) berlaku sampai tanggal yang tertera dan mengikuti lingkup pekerjaan di dokumen penawaran.</p>
<h2>7. Instalasi & penggunaan</h2>
<p>Pemasangan sistem kelistrikan (PLTS, inverter, baterai) wajib dilakukan oleh teknisi yang kompeten sesuai standar keselamatan. Kerusakan akibat pemasangan atau pemakaian yang tidak sesuai panduan di luar tanggung jawab kami.</p>
<h2>8. Batasan tanggung jawab</h2>
<p>Tanggung jawab kami terbatas pada nilai produk yang dibeli. Kami tidak bertanggung jawab atas kerugian tidak langsung (kehilangan pendapatan, data, atau keuntungan).</p>
<h2>9. Hukum yang berlaku</h2>
<p>Syarat & ketentuan ini tunduk pada hukum Republik Indonesia. Perselisihan diselesaikan secara musyawarah terlebih dahulu.</p>
<h2>10. Perubahan</h2>
<p>Syarat & ketentuan dapat diperbarui sewaktu-waktu; versi terbaru selalu tersedia di halaman ini.</p>
HTML.$kontak],

            'kebijakan-privasi' => ['Kebijakan Privasi', 'Bagaimana Energi.Click mengumpulkan, memakai dan melindungi data pribadi pelanggan sesuai UU Pelindungan Data Pribadi.', <<<'HTML'
<p>{perusahaan} ("kami") menghormati privasi Anda dan mengelola data pribadi sesuai Undang-Undang No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.</p>
<h2>Data yang kami kumpulkan</h2>
<ul>
<li>Data akun & pesanan: nama, nomor WhatsApp/telepon, email, alamat pengiriman, nama perusahaan & NPWP (bila diisi).</li>
<li>Riwayat transaksi, bukti pembayaran, dan percakapan dengan CS (chat toko / WhatsApp).</li>
<li>Data teknis: alamat IP, jenis perangkat, halaman yang dikunjungi, serta sumber kunjungan (cookie & alat analitik seperti Google dan Meta).</li>
</ul>
<h2>Untuk apa data dipakai</h2>
<ul>
<li>Memproses pesanan, verifikasi pembayaran, pengiriman, retur, dan klaim garansi.</li>
<li>Mengirim informasi status pesanan lewat WhatsApp, email, atau notifikasi akun.</li>
<li>Mengirim info promo <strong>hanya bila Anda memberi izin</strong>; balas <strong>STOP</strong> kapan saja untuk berhenti.</li>
<li>Meningkatkan layanan, keamanan, dan mencegah penipuan.</li>
</ul>
<h2>Berbagi data</h2>
<p>Kami <strong>tidak menjual</strong> data pribadi. Data hanya dibagikan seperlunya kepada kurir/ekspedisi (untuk pengiriman), penyedia layanan pembayaran & pesan, produsen (untuk klaim garansi), atau pihak berwenang bila diwajibkan hukum.</p>
<h2>Penyimpanan & keamanan</h2>
<p>Data disimpan di server dengan akses terbatas. Data transaksi disimpan selama diperlukan untuk keperluan pesanan, garansi, dan kewajiban perpajakan; log percakapan & kunjungan dihapus berkala.</p>
<h2>Hak Anda</h2>
<p>Anda dapat meminta akses, perbaikan, atau penghapusan data pribadi, serta menarik izin promo, dengan menghubungi kami.</p>
HTML.$kontak],

            'kebijakan-pengiriman' => ['Kebijakan Pengiriman', 'Waktu proses, kurir & kargo, ongkos kirim, ambil di gudang, asuransi dan penanganan barang rusak saat pengiriman di Energi.Click.', <<<'HTML'
<h2>Waktu proses</h2>
<ul>
<li>Pesanan diproses <strong>1–3 hari kerja</strong> setelah pembayaran terverifikasi, kecuali produk dengan estimasi khusus (indent/pre-order) yang tertera di halaman produk.</li>
<li>Barang dicek dan didokumentasikan sebelum dikirim; fotonya dapat dilihat di halaman lacak pesanan.</li>
</ul>
<h2>Metode pengiriman</h2>
<ul>
<li><strong>Kurir ekspedisi</strong> — ongkos kirim dihitung otomatis saat checkout berdasarkan berat aktual atau volumetrik (mana yang lebih besar).</li>
<li><strong>Kargo</strong> — untuk barang besar/berat (baterai ESS, paket PLTS, panel surya dalam jumlah banyak). Ongkos kirim <strong>dikonfirmasi admin</strong> sebelum Anda membayar.</li>
<li><strong>Ambil di gudang</strong> — bila tersedia di checkout, tanpa ongkos kirim. Bawa nomor pesanan saat pengambilan.</li>
</ul>
<h2>Nomor resi & pelacakan</h2>
<p>Nomor resi dikirim lewat notifikasi dan dapat dilihat di halaman lacak pesanan. Estimasi tiba mengikuti layanan ekspedisi dan dapat terlambat karena kondisi di luar kendali kami (cuaca, hari libur, kendala ekspedisi).</p>
<h2>Asuransi & barang rusak saat pengiriman</h2>
<ul>
<li>Panel surya dan barang bernilai tinggi disarankan menggunakan asuransi pengiriman.</li>
<li>Wajib merekam <strong>video unboxing tanpa jeda</strong> sejak paket belum dibuka.</li>
<li>Laporkan kerusakan atau kekurangan barang maksimal <strong>2 × 24 jam</strong> setelah paket diterima, disertai video & foto; kami bantu proses klaim ke ekspedisi atau penggantian.</li>
</ul>
<h2>Alamat pengiriman</h2>
<p>Pastikan alamat & nomor penerima benar. Biaya pengiriman ulang akibat alamat salah atau penerima tidak dapat dihubungi ditanggung pembeli.</p>
HTML.$kontak],

            'kebijakan-retur' => ['Kebijakan Retur & Pengembalian Dana', 'Syarat retur, barang yang tidak dapat diretur, cara mengajukan dan pengembalian dana di Energi.Click.', <<<'HTML'
<h2>Barang dapat diretur bila</h2>
<ul>
<li>Barang <strong>cacat produksi</strong> atau tidak berfungsi saat diterima.</li>
<li>Barang <strong>tidak sesuai pesanan</strong> (salah tipe, salah kapasitas, kurang jumlah).</li>
<li>Barang <strong>rusak dalam pengiriman</strong>.</li>
</ul>
<h2>Syarat pengajuan</h2>
<ul>
<li>Diajukan maksimal <strong>7 hari</strong> setelah barang diterima (kerusakan pengiriman maksimal 2 × 24 jam).</li>
<li>Menyertakan nomor pesanan, <strong>video unboxing tanpa jeda</strong>, dan foto/video kerusakan.</li>
<li>Barang, kemasan, aksesori, buku manual, dan bonus dikembalikan lengkap.</li>
</ul>
<h2>Tidak dapat diretur</h2>
<ul>
<li>Berubah pikiran / salah memilih produk setelah barang dikirim.</li>
<li>Kerusakan akibat pemasangan yang tidak sesuai standar, salah pemakaian, korsleting, lonjakan tegangan, petir, air, atau bencana.</li>
<li>Barang yang sudah dimodifikasi, nomor seri hilang/rusak.</li>
<li>Produk clearance / bekas proyek yang kondisinya sesuai deskripsi, serta barang pesanan khusus (indent/custom).</li>
</ul>
<p>Kerusakan setelah barang dipakai di luar ketentuan di atas ditangani lewat <a href="/halaman/informasi-garansi">klaim garansi</a>.</p>
<h2>Cara mengajukan</h2>
<ol>
<li>Hubungi CS via WhatsApp {whatsapp} dengan nomor pesanan, video unboxing, dan foto/video kendala.</li>
<li>Tim kami memverifikasi dalam 1–3 hari kerja dan memberi instruksi pengiriman balik bila disetujui.</li>
<li>Setelah barang diterima & diperiksa, kami kirim unit pengganti atau mengembalikan dana.</li>
</ol>
<h2>Pengembalian dana</h2>
<ul>
<li>Dana dikembalikan ke rekening atas nama pembeli dalam <strong>3–14 hari kerja</strong> setelah retur disetujui.</li>
<li>Ongkos kirim retur ditanggung kami bila kesalahan berasal dari kami (salah kirim, cacat produksi, rusak pengiriman).</li>
</ul>
HTML.$kontak],

            'informasi-garansi' => ['Informasi Garansi', 'Ketentuan garansi produk Energi.Click: masa garansi, cara klaim, dan hal yang tidak ditanggung garansi.', <<<'HTML'
<h2>Masa garansi</h2>
<p>Setiap produk bergaransi sesuai ketentuan produsen; <strong>masa garansi tercantum di halaman produk</strong> (mis. garansi produk & garansi performa untuk panel surya, garansi unit untuk inverter dan baterai). Garansi dihitung sejak tanggal invoice.</p>
<h2>Cara klaim</h2>
<ol>
<li>Hubungi CS via WhatsApp {whatsapp} dengan nomor pesanan/invoice, nomor seri, serta foto/video kendala.</li>
<li>Tim teknis kami membantu diagnosa awal (sering kali kendala bisa diselesaikan lewat pengaturan).</li>
<li>Bila perlu, barang dikirim ke kami/service center untuk pemeriksaan. Keputusan perbaikan, penggantian komponen, atau penggantian unit mengikuti kebijakan produsen.</li>
<li>Ongkos kirim barang klaim ke kami ditanggung pembeli; pengiriman kembali setelah perbaikan ditanggung kami.</li>
</ol>
<h2>Tidak ditanggung garansi</h2>
<ul>
<li>Kerusakan fisik (pecah, penyok, terbakar), terkena air, atau bencana alam/petir.</li>
<li>Pemasangan tidak sesuai standar atau spesifikasi (salah tegangan, kabel tidak sesuai, tanpa proteksi).</li>
<li>Pemakaian melebihi kapasitas, modifikasi, atau perbaikan oleh pihak yang tidak berwenang.</li>
<li>Penurunan kapasitas wajar akibat pemakaian (keausan normal) di luar ketentuan produsen.</li>
</ul>
HTML.$kontak],

            'panduan-belanja' => ['Panduan Belanja', 'Langkah berbelanja di Energi.Click: cari produk, checkout, bayar, dan lacak pesanan.', <<<'HTML'
<ol>
<li><strong>Cari produk</strong> lewat kolom pencarian, kategori, atau brand. Bingung memilih? Chat CS via WhatsApp {whatsapp}.</li>
<li><strong>Tambah ke keranjang</strong> — pilih varian (kapasitas/tipe) bila ada.</li>
<li><strong>Checkout</strong> — isi alamat, pilih pengiriman (kurir, kargo, atau ambil di gudang) dan metode pembayaran.</li>
<li><strong>Bayar</strong> dalam {batas_bayar} dan unggah bukti transfer. Untuk barang kargo, tunggu konfirmasi ongkir dari admin sebelum membayar.</li>
<li><strong>Lacak pesanan</strong> di menu Akun → Pesanan atau tautan lacak yang kami kirim; nomor resi muncul setelah barang dikirim.</li>
</ol>
<p>Butuh banyak barang atau harga proyek? Gunakan <a href="/permintaan-penawaran">Permintaan Penawaran</a>.</p>
HTML],

            'panduan-pembayaran' => ['Panduan Pembayaran', 'Metode pembayaran di Energi.Click: transfer bank dan QRIS, batas waktu bayar, PPN dan faktur pajak.', <<<'HTML'
<h2>Metode pembayaran</h2>
<ul>
<li><strong>Transfer bank</strong> ke rekening atas nama perusahaan yang tertera di halaman pembayaran pesanan.</li>
<li><strong>QRIS</strong> — scan kode QR di halaman pembayaran dengan aplikasi bank atau e-wallet.</li>
</ul>
<h2>Langkah</h2>
<ol>
<li>Setelah checkout, buka halaman pembayaran pesanan dan transfer sesuai <strong>total tagihan</strong>.</li>
<li>Unggah bukti pembayaran di halaman tersebut.</li>
<li>Pembayaran diverifikasi pada jam operasional ({jam_operasional}); status pesanan berubah menjadi diproses.</li>
</ol>
<h2>Penting</h2>
<ul>
<li>Pesanan yang belum dibayar dalam {batas_bayar} dibatalkan otomatis.</li>
<li>Harga produk <strong>belum termasuk PPN</strong>; PPN (bila berlaku) tampil terpisah di checkout dan invoice. Isi NPWP di alamat bila memerlukan faktur pajak.</li>
<li><strong>Waspada penipuan:</strong> kami tidak pernah meminta transfer ke rekening pribadi atau rekening selain yang tercantum di halaman pembayaran pesanan.</li>
</ul>
HTML.$kontak],
        ];
    }
}
