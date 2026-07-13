# Panduan Deployment — Rekasurya Store

Panduan langkah demi langkah untuk men-deploy Rekasurya Store ke produksi.
Tersedia dua jalur:

- [A. VPS / Cloud Server](#a-vps--cloud-server-ubuntu--nginx) (direkomendasikan)
- [B. Shared Hosting (cPanel)](#b-shared-hosting-cpanel)

Di bagian akhir ada [checklist keamanan](#7-checklist-keamanan-produksi),
[cara update](#8-update--redeploy), [backup](#9-backup), dan
[troubleshooting](#10-troubleshooting).

> **Prinsip penting:** aset frontend **dikompilasi lebih dulu** (`npm run build`),
> sehingga server produksi **tidak perlu menjalankan Node.js**. Cukup ada folder
> `public/build/` (sudah ikut di repo, atau bangun ulang saat rilis).

---

## Ringkasan kebutuhan

| Komponen | Versi minimum |
|----------|---------------|
| PHP | 8.3+ (dev di 8.4) + ekstensi: `pdo_mysql mbstring openssl tokenizer xml ctype json bcmath fileinfo gd zip curl` |
| Composer | 2.x |
| MySQL / MariaDB | MySQL 8+ / MariaDB 10.6+ |
| Web server | Nginx (atau Apache) |
| Node.js | 18+ — **hanya untuk build aset**, tidak dibutuhkan saat runtime |

---

## A. VPS / Cloud Server (Ubuntu + Nginx)

Contoh memakai Ubuntu 22.04, domain `store.rekasurya.co.id`, dan user `deploy`.

### 1. Siapkan server & paket

```bash
sudo apt update && sudo apt upgrade -y

# PHP 8.3 + FPM + ekstensi
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl \
  nginx mysql-server git unzip

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# (opsional, untuk build aset di server) Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 2. Buat database MySQL

```bash
sudo mysql
```
```sql
CREATE DATABASE rekasurya_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'rekasurya'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON rekasurya_store.* TO 'rekasurya'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Ambil kode & install dependency

```bash
cd /var/www
sudo git clone <URL_REPO> rekasurya
sudo chown -R deploy:www-data rekasurya
cd rekasurya

composer install --no-dev --optimize-autoloader
```

### 4. Konfigurasi `.env`

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Isi minimal yang **wajib** diubah untuk produksi:

```env
APP_NAME="Rekasurya Store"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://store.rekasurya.co.id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rekasurya_store
DB_USERNAME=rekasurya
DB_PASSWORD=GANTI_PASSWORD_KUAT

# Keamanan sesi di belakang HTTPS
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true

# Antrian & cache (database sudah cukup; pakai redis bila tersedia)
QUEUE_CONNECTION=database
CACHE_STORE=database

# Jangan tampilkan kredensial demo di produksi!
DEMO_EXPOSE_CREDENTIALS=false

# Data perusahaan & WhatsApp (juga bisa diubah via Admin → Pengaturan)
COMPANY_LEGAL_NAME="PT Rekasurya Primadaya"
COMPANY_EMAIL="sales@rekasurya.co.id"
WHATSAPP_NUMBER=628xxxxxxxxxx
PPN_PERCENT=11

# Secret gateway pembayaran — isi hanya di server, jangan commit
DEMO_GATEWAY_SECRET=
```

> **Email:** untuk notifikasi email, set `MAIL_MAILER=smtp` + kredensial SMTP Anda.
> Default `log` hanya menulis ke file log.

### 5. Migrasi database

```bash
php artisan migrate --force
```

Jika ingin memuat data contoh (kategori/produk demo — **jangan** di toko sungguhan
yang sudah berisi data), tambahkan `--seed`. Untuk produksi bersih, minimal jalankan
seeder inti agar role/pengaturan/ongkir tersedia:

```bash
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=SettingSeeder --force
php artisan db:seed --class=WarehouseSeeder --force
php artisan db:seed --class=ShippingSeeder --force
```

Lalu buat akun admin pertama (lihat [langkah 11](#11-buat-akun-admin-pertama)).

### 6. Symlink storage & permission

```bash
php artisan storage:link

# Folder yang harus bisa ditulis web server
sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 7. Build aset frontend

**Opsi 1 — build di server** (jika Node terpasang):
```bash
npm ci && npm run build
```

**Opsi 2 — build di lokal lalu unggah** (server tanpa Node):
```bash
# di komputer Anda
npm ci && npm run build
# lalu salin folder public/build/ ke server:
scp -r public/build deploy@server:/var/www/rekasurya/public/
```

### 8. Optimasi cache Laravel

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> Ulangi tiga perintah ini **setiap kali** ada perubahan `.env`/route/view saat rilis.

### 9. Konfigurasi Nginx

`sudo nano /etc/nginx/sites-available/rekasurya`:

```nginx
server {
    listen 80;
    server_name store.rekasurya.co.id;
    root /var/www/rekasurya/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }

    client_max_body_size 20M;   # untuk unggah datasheet/BOQ/foto
}
```

Aktifkan & reload:

```bash
sudo ln -s /etc/nginx/sites-available/rekasurya /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 10. HTTPS (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d store.rekasurya.co.id
```

Certbot menulis konfigurasi SSL otomatis dan memasang auto-renew. Setelah HTTPS
aktif, pastikan `APP_URL=https://...` dan `SESSION_SECURE_COOKIE=true`, lalu
`php artisan config:cache`.

### 11. Buat akun admin pertama

```bash
php artisan tinker
```
```php
$u = App\Models\User::create([
  'name' => 'Administrator',
  'email' => 'admin@rekasurya.co.id',
  'password' => 'PASSWORD_KUAT',
  'is_staff' => true,
  'is_active' => true,
]);
$u->assignRole('super-admin');
```

Login di `https://store.rekasurya.co.id/masuk`, panel admin ada di `/admin`.

### 12. Queue worker (systemd)

Notifikasi dan tugas berat berjalan lewat antrian. Buat service systemd:

`sudo nano /etc/systemd/system/rekasurya-queue.service`:

```ini
[Unit]
Description=Rekasurya Queue Worker
After=network.target mysql.service

[Service]
User=deploy
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/rekasurya
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --timeout=90 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now rekasurya-queue
sudo systemctl status rekasurya-queue
```

> Alternatif memakai **Supervisor**: buat program dengan `command=php artisan
> queue:work ...`, `autostart=true`, `autorestart=true`, `numprocs=1`.
> Bila server sangat terbatas, set `QUEUE_CONNECTION=sync` agar job jalan langsung
> (tanpa worker), dengan konsekuensi request checkout sedikit lebih lambat.

### 13. Scheduler (cron)

Scheduler menjalankan `stock:release-expired` (melepas stok yang direservasi tapi
tak jadi dibayar) setiap 5 menit. Tambahkan **satu** baris cron:

```bash
crontab -e
```
```cron
* * * * * cd /var/www/rekasurya && php artisan schedule:run >> /dev/null 2>&1
```

Selesai — toko sudah live. 🎉

---

## B. Shared Hosting (cPanel)

Shared hosting umumnya tak bisa mengarahkan document root ke `public/`. Dua cara:

### Cara 1 — arahkan domain ke folder `public/` (paling bersih)
1. Unggah seluruh proyek ke luar `public_html`, mis. `~/rekasurya`.
2. Di cPanel → **Domains**, arahkan domain/subdomain ke `~/rekasurya/public`.

### Cara 2 — pindahkan isi `public/` ke `public_html`
1. Unggah proyek ke `~/rekasurya` (di luar web root), lalu pindahkan **isi** folder
   `public/` ke `~/public_html`.
2. Edit `~/public_html/index.php`, ubah dua path agar menunjuk ke aplikasi:
   ```php
   require __DIR__.'/../rekasurya/vendor/autoload.php';
   $app = require_once __DIR__.'/../rekasurya/bootstrap/app.php';
   ```
3. Build aset di lokal (`npm run build`) lalu unggah `public/build/` ke
   `~/public_html/build/`.
4. Buat database MySQL di cPanel (**MySQL Databases**), catat nama db/user/password,
   isikan ke `.env`.
5. Jalankan migrasi lewat SSH (jika tersedia): `php artisan migrate --force`, lalu
   seeder inti (RoleSeeder, SettingSeeder, WarehouseSeeder, ShippingSeeder).
   Jika tak ada SSH, gunakan fitur **Terminal** cPanel atau minta hosting menjalankannya.
6. `php artisan storage:link` — bila `symlink()` diblokir, buat symlink manual di
   **File Manager** (`public_html/storage` → `~/rekasurya/storage/app/public`) atau
   salin folder tersebut.
7. **Cron:** di cPanel → **Cron Jobs**, tambahkan (tiap menit):
   ```
   cd ~/rekasurya && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
   ```
8. **Queue:** jika tak bisa menjalankan worker terus-menerus, set
   `QUEUE_CONNECTION=sync` di `.env`.

Karena aset sudah dikompilasi, hosting **tidak perlu Node.js**.

---

## 7. Checklist keamanan produksi

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_KEY` sudah di-generate
- [ ] `APP_URL` memakai `https://` dan `SESSION_SECURE_COOKIE=true`
- [ ] `DEMO_EXPOSE_CREDENTIALS=false` (sembunyikan kredensial demo)
- [ ] Password akun demo diganti / akun demo dinonaktifkan atau dihapus
- [ ] Kredensial DB & `DEMO_GATEWAY_SECRET`/API gateway hanya di `.env` (tak di-commit)
- [ ] File `.env` tidak dapat diakses publik (root Nginx sudah di `public/`)
- [ ] Folder `storage/` & `bootstrap/cache/` writable oleh `www-data`, sisanya tidak
- [ ] `config:cache`, `route:cache`, `view:cache` sudah dijalankan
- [ ] HTTPS aktif + auto-renew (Certbot)
- [ ] Backup DB & folder `storage/app/public` terjadwal
- [ ] Webhook pembayaran diarahkan ke `/webhook/pembayaran/{provider}` dan
      `DEMO_GATEWAY_SECRET` (atau secret gateway asli) telah diisi

---

## 8. Update / redeploy

```bash
cd /var/www/rekasurya

# (opsional) mode maintenance saat rilis
php artisan down --render="errors::503"

git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force

# jika ada perubahan aset:
npm ci && npm run build      # atau unggah public/build/ dari lokal

# segarkan cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# restart worker agar memuat kode baru
sudo systemctl restart rekasurya-queue

php artisan up
```

Contoh `deploy.sh` sederhana bisa membungkus langkah di atas untuk sekali jalan.

---

## 9. Backup

```bash
# Database (jadwalkan via cron harian, simpan off-server)
mysqldump -u rekasurya -p rekasurya_store | gzip > /backups/db-$(date +%F).sql.gz

# Restore
gunzip < /backups/db-YYYY-MM-DD.sql.gz | mysql -u rekasurya -p rekasurya_store

# Media yang diunggah (foto produk, banner, BOQ, bukti bayar)
tar czf /backups/storage-$(date +%F).tar.gz storage/app/public
```

Contoh cron backup DB harian jam 02.00:
```cron
0 2 * * * mysqldump -u rekasurya -pPASSWORD rekasurya_store | gzip > /backups/db-$(date +\%F).sql.gz
```

---

## 10. Troubleshooting

| Gejala | Penyebab & solusi |
|--------|-------------------|
| **500 saat buka halaman** | Cek `storage/logs/laravel.log`. Sering karena permission `storage/` — jalankan `chmod -R 775 storage bootstrap/cache` & `chown -R deploy:www-data`. |
| **`Vite manifest not found`** | Aset belum dibangun. Jalankan `npm run build` (atau unggah `public/build/`). |
| **`No application encryption key`** | Jalankan `php artisan key:generate` lalu `php artisan config:cache`. |
| **Perubahan `.env` tidak berefek** | Konfigurasi ter-cache. Jalankan `php artisan config:clear` lalu `config:cache`. |
| **Gambar/upload 404** | `php artisan storage:link` belum dijalankan, atau symlink diblokir hosting (buat manual). |
| **CSS/tampilan berantakan** | Path aset salah / build lama. Pastikan `APP_URL` benar & `public/build/` ter-deploy. |
| **Halaman selain beranda 404** | `try_files ... /index.php?$query_string` belum ada di Nginx, atau `mod_rewrite`/`.htaccess` mati di Apache. |
| **Webhook pembayaran 401** | Signature invalid — pastikan `DEMO_GATEWAY_SECRET` (atau secret gateway) sama dengan yang dipakai gateway. |
| **Notifikasi tidak terkirim** | Worker antrian mati — `sudo systemctl status rekasurya-queue`, atau set `QUEUE_CONNECTION=sync`. |
| **Stok "tersangkut" ter-reservasi** | Pastikan cron `schedule:run` aktif; jalankan manual `php artisan stock:release-expired`. |

---

Untuk detail arsitektur pembayaran/pengiriman dan skema database, lihat
[`PAYMENTS.md`](PAYMENTS.md), [`SHIPPING.md`](SHIPPING.md), dan
[`DATABASE.md`](DATABASE.md).
