# Agent perbaikan otomatis energi.click

Dijalankan terjadwal (Selasa, Kamis, Ahad ±07.00 WIB). Tiap putaran: audit → 1–3 perbaikan
kecil → usulan PR → pemilik menerima laporan + link PR di WhatsApp → tayang hanya setelah
pemilik menekan **Merge** (auto-deploy).

## Alur kerja

1. Ambil branch kerja terbaru: `git fetch origin claude/rekasurya-store-ecommerce-uwqx70`,
   lalu `git checkout -B claude/auto-improve origin/claude/rekasurya-store-ecommerce-uwqx70`.
2. Bila PR lama dari `claude/auto-improve` masih terbuka (belum di-merge): tutup dengan
   komentar singkat "digantikan usulan baru", dan sebutkan di laporan bahwa usulan lama
   belum disetujui.
3. Siapkan lingkungan: `composer install`, `cp .env.example .env`, `php artisan key:generate`,
   `touch database/database.sqlite`, `php artisan migrate --seed`.
4. Audit dan pilih **1–3 perbaikan kecil** yang paling berdampak bagi pembeli, misalnya:
   - tampilan HP & desktop (screenshot Playwright: beranda, katalog, produk, keranjang, checkout),
   - teks/tombol membingungkan, link mati, gambar tanpa alt, kontras rendah, layout meluber,
   - SEO (judul/deskripsi/schema), kecepatan (query N+1, gambar berat), bug yang terlihat di test.
   Hindari mengulang perbaikan yang sudah ada di `git log`.
5. Kerjakan, lalu wajib: `vendor/bin/pint --dirty`, `php artisan test` (semua lulus), dan
   `npm run build` bila ada class Tailwind baru.
6. Tulis `deploy/PROPOSAL.md` (dibaca pemilik di WhatsApp — Bahasa Indonesia santai, singkat):
   - maksimal 6 baris poin: apa yang diubah & manfaatnya bagi pembeli,
   - 1 baris "Risiko:" (mis. "rendah — hanya tampilan"),
   - opsional 1–2 ide besar untuk dibahas (tidak dikerjakan).
7. Commit (pesan Bahasa Indonesia), `git push -f -u origin claude/auto-improve`, buat PR ke
   `claude/rekasurya-store-ecommerce-uwqx70`.
8. Tambahkan baris `PR: <url PR>` di akhir `deploy/PROPOSAL.md`, commit, push lagi. Server
   mengirim WA ke pemilik hanya setelah baris PR ada.

## Larangan

- Jangan push ke `claude/rekasurya-store-ecommerce-uwqx70` atau branch lain selain `claude/auto-improve`.
- Jangan mengubah harga, modal, stok, produk/katalog, isi halaman kebijakan, data pelanggan,
  pengaturan pembayaran/pajak, atau menambah seeder di `deploy/seeders.txt`.
- Jangan menambah fitur besar, dependensi baru, atau migrasi yang menghapus/mengubah kolom.
- Jangan commit token/password/API key. Jangan menulis identifier model AI di commit/kode.
- Ikuti CLAUDE.md (gaya kode, aturan harga & keamanan).
- Bila tidak ada perbaikan yang aman & bermanfaat: jangan buat PR, akhiri saja.
