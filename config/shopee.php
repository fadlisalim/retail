<?php

/*
|--------------------------------------------------------------------------
| Ekspor Mass Upload Shopee
|--------------------------------------------------------------------------
| Katalog Energi.Click → template "Shopee mass upload (basic)". File
| template asli Shopee ada di resources/shopee/mass_upload_template.xlsx;
| sheet "Template" diisi mulai baris 7. Jalankan: php artisan shopee:export
| atau tombol "Ekspor Shopee" di Admin → Produk.
*/
return [

    'template' => resource_path('shopee/mass_upload_template.xlsx'),

    // Kode kategori Shopee per slug kategori kita (kolom "Kategori" opsional —
    // kosong = Shopee merekomendasikan sendiri saat upload). Isi bila sudah
    // tahu kodenya dari Seller Centre → Daftar Kategori. Kunci = slug kategori
    // (atau awalan slug, mis. "kabel-konektor-proteksi" mencakup sub-kategorinya).
    'categories' => [
        'baterai' => '100043',                 // Elektronik/Baterai
        'pompa-air-tenaga-surya' => '101192',  // Perlengkapan Rumah/.../Pompa Air & Aksesoris
        'kabel-konektor-proteksi' => '100220', // Elektronik/Kelistrikan/Kelistrikan Lainnya
    ],

    // Produk berbahaya (baterai/magnet/cairan) → kolom "Produk Berbahaya" Yes (ID).
    'dangerous_pattern' => '/baterai|battery|lithium|lifepo|power ?wall|power ?station|powerbank|\baki\b/i',

    // Jasa kirim yang diaktifkan per produk (Aktif/Nonaktif). Reguler dibatasi
    // berat; Hemat Kargo (JTR dll.) untuk yang berat.
    'channels' => [
        '8003' => ['label' => 'Reguler (Cashless)', 'max_grams' => 50000],
        '8005' => ['label' => 'Hemat Kargo', 'max_grams' => null],
    ],

    // Batas Shopee (dari template): harga, rasio harga termahal/termurah per
    // produk, panjang teks.
    'limits' => [
        'price_min' => 99,
        'price_max' => 150_000_000,
        'price_ratio' => 7,
        'name_max' => 255,
        'description_max' => 3000,
        'variation_name_max' => 14,
        'variation_option_max' => 20,
        'images_max' => 8,
    ],
];
