<?php

namespace App\Models;

use App\Services\SettingService;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'title', 'slug', 'content', 'is_published',
        'meta_title', 'meta_description', 'og_image_path',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Konten siap tampil: penanda {brand}, {perusahaan}, {alamat}, {email}, {telepon},
     * {whatsapp}, {jam_operasional}, {batas_bayar} diganti data terkini dari Pengaturan —
     * jadi alamat/kontak di halaman kebijakan selalu sama dengan footer & invoice.
     */
    public function renderedContent(): string
    {
        $settings = app(SettingService::class);
        $company = $settings->company();
        $wa = preg_replace('/\D/', '', $settings->whatsappNumber());
        $waLabel = str_starts_with($wa, '62') ? '0'.substr($wa, 2) : $wa;
        $hours = (int) config('rekasurya.orders.payment_window_hours', 168);

        return strtr((string) $this->content, [
            '{brand}' => e(brand()),
            '{perusahaan}' => e((string) $company['legal_name']),
            '{alamat}' => e((string) $company['address']),
            '{email}' => '<a href="mailto:'.e((string) $company['email']).'">'.e((string) $company['email']).'</a>',
            '{telepon}' => e((string) $company['phone']),
            '{whatsapp}' => '<a href="https://wa.me/'.$wa.'" target="_blank" rel="noopener">'.e($waLabel).'</a>',
            '{jam_operasional}' => e((string) config('rekasurya.whatsapp.hours', 'Senin–Sabtu, 08.00–17.00 WIB')),
            '{batas_bayar}' => $hours % 24 === 0 ? ($hours / 24).' × 24 jam' : $hours.' jam',
        ]);
    }
}
