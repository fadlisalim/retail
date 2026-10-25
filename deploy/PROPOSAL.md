# Beranda: 4 query banner jadi 1

## Ringkasan
- Beranda mengambil semua banner (hero, grid, video, penawaran) dalam 1 query, sebelumnya 4 — halaman utama sedikit lebih ringan di database.
- Tampilan & isi beranda sama persis; tidak ada perubahan harga/data.

## Temuan audit
- Ukur lokal (15 halaman publik, DB seed): beranda 50 query (warm; 59 saat sesi baru), produk 51, kategori/{slug} 29, halaman lain 14–23. Tidak ada N+1 (query duplikat maksimal 2–4× karena beda posisi banner/cache).
- Beranda memanggil `Banner::active()` 4× (hero, grid, video, quotation), tiap kali query sendiri.
- Sebelum → sesudah: beranda 50 → 47 query (warm).
- Saran pemilik di PR #1, #2, #3: tidak ada. PR #3 (usulan yang sama) masih terbuka, ditutup dan digantikan PR ini (basis diperbarui ke branch kerja terbaru).

## Perubahan
- `HomeController`: satu query banner aktif dengan `whereIn('position', …)`, dikelompokkan per posisi di PHP. Variabel `$featuredCard` yang tidak terpakai dihapus.
- Test baru `HomeBannerQueryTest` (1 query banner, tiap posisi benar, banner nonaktif tidak tampil). 516 test lulus.

## Risiko
- Rendah — hasil banner per posisi identik (urutan `sort_order` tetap), hanya cara mengambilnya.

## Ide berikutnya
- Beranda & produk masih ±50 query: kategori root diambil 2× (header dan beranda) dan 5 rak produk punya relasi sendiri-sendiri — bisa dipangkas lagi atau di-cache 1–2 menit untuk pengunjung tanpa login.
- Konversi foto produk ke WebP + srcset (ukur dulu bobot foto di produksi).

PR: https://github.com/fadlisalim/retail/pull/4
