# Halaman toko lebih ringan: query database turun 30–45%

## Ringkasan
- Beranda kini butuh 49 query database (sebelumnya 82) — halaman lebih cepat terbuka, terutama saat ramai pengunjung.
- Halaman kategori 50 → 29 query, halaman produk 61 → 45–50, keranjang 25 → 16.
- Tidak ada perubahan tampilan, harga, atau data — murni kecepatan di sisi server.

## Temuan audit
| Halaman | Query sebelum | Query sesudah |
|---|---|---|
| Beranda | 82 | 49 |
| Kategori (Panel Surya) | 50 | 29 |
| Produk (PJU Aurora Optimus LS) | 61 | 45 |
| Produk (Panel 550Wp) | 61 | 50 |
| Keranjang | 25 | 16 |
| Promo | 36 | 23 |
| Brand | 23 | 14 |

Penyebab (diukur dengan data contoh lokal):
- Tiap kartu produk mengambil detail kondisi (badge "Harga Nego") satu per satu → 29 query ekstra di beranda; makin banyak produk, makin lambat.
- Data header/menu (kategori, jumlah keranjang, wishlist, banding) dihitung 2–3× per halaman.
- Halaman kategori mengambil sub-kategori satu per satu (10 query) untuk menyaring produk.

## Perubahan
- Kartu produk: relasi yang dibutuhkan (brand, kategori, detail kondisi) diambil sekaligus di beranda, pencarian/katalog, dan produk terkait.
- Header toko: data menu & penghitung dihitung sekali per halaman.
- Kategori: semua sub-kategori dibaca dengan 1 query.
- Test baru: jumlah query beranda tidak boleh naik saat produk bertambah.

## Risiko
- Rendah — logika tampilan sama persis; semua 507 test lulus.

## Ide berikutnya
- Ukur kecepatan muat nyata di HP (foto produk & ukuran halaman beranda ±220 KB HTML) — kandidat audit berikutnya.
- Simpan cache halaman beranda untuk pengunjung tanpa login selama 1–2 menit.
