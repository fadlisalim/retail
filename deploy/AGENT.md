# Agent audit & perbaikan otomatis energi.click

Jalan **tiap hari pagi** (tugas terjadwal). Tiap putaran:
audit → 1–3 perbaikan → usulan (PR, belum tayang) → server energi.click mengirim WA ke
pemilik berisi ringkasan + link halaman laporan `energi.click/usulan/…` → pemilik memberi
saran dan menekan **Eksekusi** (OTP WhatsApp) → PR di-merge → auto-deploy menayangkan.
Agent TIDAK PERNAH me-merge sendiri.

## Alur kerja

1. Ambil branch kerja terbaru: `git fetch origin claude/rekasurya-store-ecommerce-uwqx70`,
   lalu `git checkout -B claude/auto-improve origin/claude/rekasurya-store-ecommerce-uwqx70`.
2. **Baca saran pemilik dulu**: lihat 5 PR terakhir dari head `claude/auto-improve` (terbuka,
   ditutup, maupun di-merge). Baca komentar berawalan **"Saran pemilik"**:
   - PR ditutup + saran (revisi/tolak) → kerjakan ulang sesuai saran, atau jangan ulangi bila ditolak.
   - PR di-merge + saran → jadikan prioritas putaran ini.
   Bila PR sebelumnya masih terbuka: tutup dengan komentar "digantikan usulan baru", lalu sebut di laporan.
3. Siapkan lingkungan: `composer install`, `cp .env.example .env`, `php artisan key:generate`,
   `touch database/database.sqlite`, `php artisan migrate --seed`, `npm ci && npm run build`.
4. **Audit** (ukur, jangan menebak), dengan `php artisan serve` + Playwright (Chromium terpasang):
   - **Performa**: waktu muat & ukuran transfer beranda, katalog, produk, keranjang, checkout
     (HP 390px & desktop 1366px); jumlah query per halaman (N+1); gambar berat; cache.
   - **Tampilan & kenyamanan**: screenshot HP & desktop; teks terpotong, layout meluber, kontras,
     ukuran tombol sentuh, konsistensi.
   - **Efektivitas**: kejelasan harga/stok/ongkir/garansi, CTA (beli, WA), alur checkout, kepercayaan.
   - **Efisiensi & informatif**: langkah yang bisa dipangkas, info yang dicari pembeli tapi tidak ada,
     link mati, alt gambar, SEO (judul/deskripsi/schema).
5. Pilih **1–3 perbaikan** paling berdampak bagi customer. Kerjakan, lalu wajib:
   `vendor/bin/pint --dirty`, `php artisan test` (semua lulus), `npm run build` bila ada class Tailwind baru.
   Ukur lagi setelah perbaikan (angka sebelum → sesudah).
6. Tulis `deploy/PROPOSAL.md` (Markdown, Bahasa Indonesia santai, dibaca pemilik di HP):

   ```
   # <judul singkat usulan>

   ## Ringkasan
   - 2–4 poin: apa yang berubah & manfaatnya bagi customer (bagian ini yang dikirim ke WA)

   ## Temuan audit
   - temuan + angka (mis. "katalog HP 2,1 dtk → 1,3 dtk", "18 query → 6 query")

   ## Perubahan
   - detail perubahan per halaman

   ## Risiko
   - rendah/sedang + alasan

   ## Ide berikutnya
   - 1–3 ide besar untuk dibahas (tidak dikerjakan)
   ```
7. Commit (pesan Bahasa Indonesia), `git push -f -u origin claude/auto-improve`, buat PR ke
   `claude/rekasurya-store-ecommerce-uwqx70` (judul = judul usulan).
8. Tambahkan baris terakhir `PR: <url PR>` di `deploy/PROPOSAL.md`, commit, push lagi. Server
   baru mengirim WA setelah baris PR ada.

## Larangan

- Jangan push ke branch selain `claude/auto-improve`. Jangan me-merge PR sendiri.
- Jangan mengubah harga, modal, stok, produk/katalog, isi halaman kebijakan, data pelanggan,
  pengaturan pembayaran/pajak, atau menambah seeder di `deploy/seeders.txt`.
- Jangan menambah fitur besar, dependensi baru, atau migrasi yang menghapus/mengubah kolom.
- Jangan commit token/password/API key. Jangan menulis identifier model AI di commit/kode.
- Ikuti CLAUDE.md (gaya kode, aturan harga & keamanan).
- Bila tidak ada perbaikan yang aman & bermanfaat: jangan buat PR, akhiri saja.
