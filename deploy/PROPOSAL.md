# Logo header & footer 6× lebih ringan

## Ringkasan
- Logo di header dan footer kini 8,8 KB (sebelumnya 56 KB, dimuat 2×) — halaman terbuka lebih hemat kuota, terutama di HP.
- Logo punya ukuran eksplisit sehingga tata letak tidak bergeser saat memuat; logo footer baru dimuat saat discroll.
- Tampilan sama persis, tidak ada perubahan harga/data.

## Temuan audit
- Beranda: HTML 216 KB, 0,43 dtk lokal (setelah usulan sebelumnya); 28 gambar, 24 sudah lazy, semua punya alt.
- Logo `logo.png` 512×512 (56 KB) dipakai di header dan footer pada ukuran tampil 36 px → 2 unduhan ±112 KB, footer ikut dimuat di awal.
- Sebelum → sesudah: bobot logo per halaman 112 KB → 9 KB (header 8,8 KB + footer lazy).

## Perubahan
- File baru `public/images/logo-sm.png` (128 px, cukup untuk layar 3×). `logo.png` asli tetap dipakai PDF penawaran & gambar OG.
- `components/logo.blade.php`: pakai logo-sm, `width/height=128`, `decoding="async"`, opsi `lazy`.
- Footer memakai `:lazy="true"`.
- Test baru `LogoComponentTest`. 510 test lulus.

## Risiko
- Rendah — hanya mengganti file gambar yang sama versi kecil; logo di admin/auth ikut komponen yang sama (tampil ≤ 40 px).

## Ide berikutnya
- Cache halaman beranda untuk pengunjung tanpa login 1–2 menit.
- Konversi foto produk ke WebP + ukuran responsif (srcset) — ukur dulu bobot foto di produksi.
