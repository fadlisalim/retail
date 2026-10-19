# Instruksi untuk Claude di repo ini

## Gaya balasan (WAJIB)
- Mode **caveman** selalu aktif (skill `anthropic-skills:caveman`, mode NORMAL): tanpa basa-basi, tanpa pengulangan, kalimat pendek/fragmen. Bahasa Indonesia santai ("bro").
- Blok kode, perintah shell, dan pesan error tetap LENGKAP dan persis. Hemat kata hanya di luar kode.
- Jangan merangkum ulang yang sudah dikatakan. Jawab dulu, baru 1 insight bila perlu.

## Konteks proyek
- Energi.Click by Rekasurya — Laravel 13 + MySQL, Tailwind v4, Alpine. Produksi: energi.click (CyberPanel/LiteSpeed), repo `/home/energi.click/retail`, pemilik file `energ3514`.
- Branch kerja: `claude/rekasurya-store-ecommerce-uwqx70`. Jangan push ke branch lain.
- Deploy OTOMATIS: cron `deploy/auto-deploy.sh` (user energ3514, tiap 2 menit) → pull, composer bila lock berubah, migrate, seeder di `deploy/seeders.txt`, optimize:clear. Cukup commit + push; JANGAN suruh pemilik deploy manual lewat SSH.
- Butuh seeder jalan di produksi? Tambah baris `<id-unik> <NamaSeeder>` di `deploy/seeders.txt` (sekali per id).
- Status/log deploy: Admin → Pengaturan → "Status deploy otomatis" (`storage/logs/deploy.log`).
- Perintah manual (darurat saja) sebagai user situs: `su -s /bin/bash energ3514 -c "cd /home/energi.click/retail && ..."`.
- Cron `schedule:run` tiap menit milik user `energ3514`.

## Aturan keamanan
- JANGAN pernah commit token/API key/password (Wablas, Anthropic, RajaOngkir, DB, ridl_ro). Hanya referensi `.env` + placeholder di `.env.example`.
- Harga modal supplier tidak di-commit kecuali diberikan langsung pemilik.
- Jangan tulis identifier model AI di commit/kode.

## Kebiasaan kerja
- Jalankan `vendor/bin/pint --dirty` lalu `php artisan test` sebelum commit.
- Commit message Bahasa Indonesia, singkat, deskriptif.
