# WhatsApp Campaign (promo via Wablas)

Fitur **Admin → WA Campaign** mengirim promo (teks + gambar + link produk) ke pelanggan
yang **memberi izin**, secara bertahap dan terukur, lewat akun Wablas toko.

> Tidak ada pengaturan di sini yang menjamin nomor bebas blokir WhatsApp. Batas-batas
> di bawah hanya mengurangi risiko. Meta Verified / centang hijau juga tidak menjamin.

## 1. Kredensial & mode

Kredensial hanya dibaca dari `.env` server (tidak pernah disimpan di database / tampil di admin):

```env
WABLAS_ENABLED=true
WABLAS_BASE_URL=https://xxx.wablas.com      # server akun Anda (lihat dashboard Wablas → Device)
WABLAS_TOKEN=...                              # Device → API Token
WABLAS_SECRET=...                             # Device → Secret Key (opsional; Authorization = token.secret)
WABLAS_WEBHOOK_TOKEN=...                      # acak, alfanumerik ≥ 24 karakter (openssl rand -hex 24)
WABLAS_CAMPAIGN_MOCK=false                    # true = mode mock (tidak mengirim apa pun)
```

Setelah mengubah `.env`: `php artisan optimize:clear`. Jangan tempel token di perintah shell (masuk history); edit dengan `nano .env`.

**Mode mock** aktif bila `WABLAS_CAMPAIGN_MOCK=true` **atau** `WABLAS_ENABLED=false`. Semua alur (antrean,
jam kirim, STOP, laporan) berjalan, tetapi tidak ada request ke Wablas; pesan "diterima" dengan id `MOCK-…`.
Gunakan ini saat pengembangan / sebelum kredensial siap.

TLS selalu diverifikasi (HTTP client Laravel, tidak ada opsi mematikan).

## 2. Jenis koneksi akun Wablas

Cek di dashboard Wablas → Device:

| Jenis | Ciri | Aturan di fitur ini |
|---|---|---|
| **QR / scan device** (umum) | Device disambungkan dengan scan QR dari HP | Bukan Cloud API resmi Meta. Tidak ada approval template. Nomor bisa diblokir WhatsApp bila banyak laporan spam → patuhi batas kirim, hanya kirim ke yang mengizinkan, selalu ada "Balas STOP". |
| **WhatsApp Cloud API** | Nomor terdaftar di Meta Business, template disetujui Meta | Isi pesan harus persis template yang disetujui Meta (kategori Marketing) dan memuat opsi berhenti. Pilih "Cloud API" di Pengaturan sebagai pengingat. |

Jangan menganggap koneksi QR adalah Cloud API. Pilih jenis yang benar di **WA Campaign → Pengaturan**.

## 3. Webhook (STOP, balasan, status pesan)

Di Wablas → Device → **Webhook**:

- **Webhook URL** (pesan masuk): `https://energi.click/webhook/wablas?token=WABLAS_WEBHOOK_TOKEN`
- **Tracking / status URL** (bila tersedia): URL yang sama.

Yang diproses:

- Pesan masuk `STOP` / `BERHENTI` / `UNSUBSCRIBE` → kontak jadi **opted_out**, semua antreannya dibatalkan,
  pesan yang masih pending di Wablas dicoba dibatalkan (`WABLAS_CANCEL_PATH`, default `/api/v2/cancel-message`,
  best-effort), lalu satu konfirmasi dikirim. Status STOP **tidak pernah ditimpa** oleh import/sinkron.
- Balasan lain dari penerima promo (≤ 7 hari) dihitung sebagai "balasan" di laporan.
- Payload status (`id` + `status`/`ack`: sent/delivered/read/cancel/reject) memperbarui status pesan. Idempotent,
  status tidak pernah mundur. Tanpa webhook status, laporan berhenti di "Diterima gateway" — **bukan** berarti sampai.
- Pesan masuk tetap masuk inbox **WA Chat** seperti sebelumnya.

Di produksi webhook **ditolak** bila `WABLAS_WEBHOOK_TOKEN` kosong.

## 4. Scheduler (wajib)

Dispatcher berjalan lewat scheduler Laravel tiap menit. Pastikan cron sistem ada (sudah dipakai fitur lain):

```cron
* * * * * cd /home/energi.click/retail && php artisan schedule:run >> /dev/null 2>&1
```

Perintah manual: `php artisan wa-campaign:dispatch` (putaran sekali), `php artisan wa-campaign:sync-contacts`.

## 5. Alur kerja tim sales

1. **Kontak & Izin** → "Sinkron dari data toko" (customer, pesanan lunas, lead Kirana, WA chat). Kontak hasil sinkron
   berstatus *belum ada izin* dan **tidak** dikirimi.
2. Catat izin: centang di checkout/daftar (otomatis, bukti tersimpan), import CSV (`telepon,nama,tag,minat,izin,bukti`),
   atau manual dengan bukti.
3. **Pengaturan**: jam kirim (default 09.00–18.00 WIB, Sen–Sab), maks/menit, maks/hari, jeda 7 hari per kontak,
   jeda otomatis setelah N gagal beruntun, footer STOP, nomor tes.
4. **Buat Campaign**: filter penerima → pesan (`{nama}`, `{link}`, `{produk}`, `{harga}`) → gambar upload / foto produk →
   preview WhatsApp → **Kirim tes** (hanya ke nomor tes) → Simpan & Mulai / jadwalkan.
5. Pantau di halaman campaign: antre, diterima gateway, terkirim/sampai/dibaca (bila webhook status), gagal + alasan,
   tidak pasti (timeout), balasan, STOP. Tombol **Jeda / Lanjutkan / Batalkan**, dan **Emergency Stop** global.

Link produk otomatis diberi `utm_source=whatsapp&utm_medium=campaign&utm_campaign=<slug>` sehingga kunjungan dan
pesanan dari campaign terlihat di Statistik Trafik.

## 6. Keandalan

- Antrean persisten di tabel `wa_campaign_messages`, satu baris per (campaign, kontak) — unik, tidak bisa ganda.
- Klaim baris atomik (`queued → sending`) sebelum request, jadi dua proses tidak mengirim pesan yang sama.
- Izin dan jeda 7 hari dicek ulang **tepat sebelum** kirim.
- Error sementara (5xx/429): retry terbatas (default 3×) dengan backoff 5/10 menit.
- **Timeout / hasil tak diketahui**: pesan ditandai *tidak pasti* dan **tidak dikirim ulang otomatis**. Admin cek
  di Wablas, lalu "Antre ulang" manual bila memang belum terkirim.
- Jeda otomatis bila N kegagalan beruntun atau device Wablas tidak terhubung (dicek via `/api/device/info`).
- Webhook duplikat aman (dedupe `wablas_id`, STOP idempotent, status tidak mundur).
- Semua aksi admin tercatat di Audit Log; aktivitas runtime di log campaign.

## 7. Yang sengaja tidak dibuat

Rotasi nomor, pengacakan/manipulasi teks untuk menghindari deteksi spam, pengiriman ke kontak tanpa izin,
dan mematikan verifikasi TLS.
