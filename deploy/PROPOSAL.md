# Beranda: kategori utama diambil sekali, bukan dua kali

## Ringkasan
- Header (mega menu) dan deretan kategori di beranda sekarang berbagi 1 hasil query kategori utama, sebelumnya masing-masing query sendiri.
- Beranda sedikit lebih ringan di database; tampilan & isi sama persis, tidak ada perubahan harga/data.

## Temuan audit
- Ukur lokal (DB seed, request tunggal): beranda 43 query; kategori utama (`parent_id is null`) diambil 2× (mega menu + deretan kategori beranda).
- Tidak ada N+1 lain di beranda; duplikat sisanya hanya sesi/cache (kecil).
- Sebelum → sesudah: beranda 43 → 41 query.
- Saran pemilik di PR #1–#4: tidak ada. PR #4 (banner 1 query) sudah di-merge.

## Perubahan
- `CategoryVisibility::visibleRoots()` baru: kategori utama aktif + anak aktif, difilter kategori kosong, di-memo per request.
- `StorefrontComposer` (mega menu) dan `HomeController` memakai fungsi itu.
- Test baru `HomeRootCategoriesQueryTest` (1 query kategori utama, kategori terisi tampil). 522 test lulus.

## Risiko
- Rendah — hasil kategori identik, memo hanya hidup selama satu request.

## Ide berikutnya
- Cache 1–2 menit untuk rak produk beranda (5 rak, tiap rak query + relasi) bagi pengunjung tanpa login.
- Konversi foto produk ke WebP + srcset (ukur dulu bobot foto di produksi).
