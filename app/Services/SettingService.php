<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime-editable settings with a config() fallback. Values are cached and the
 * cache is busted on write, so hot paths (header, tax, WhatsApp) avoid a query
 * per request. Never store secrets here — those live in .env.
 */
class SettingService
{
    private const CACHE_KEY = 'settings.all';

    /** @var array<string,mixed>|null */
    private ?array $cache = null;

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        return $this->cache = Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::all()->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        if (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') {
            return $all[$key];
        }

        // Fall back to config/rekasurya.php using dot notation (tax.ppn_percent -> config).
        return config('rekasurya.'.$key, $default);
    }

    public function set(string $key, mixed $value, string $type = 'string', string $group = 'general'): void
    {
        $stored = is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);

        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type, 'group' => $group],
        );

        $this->flush();
    }

    public function flush(): void
    {
        $this->cache = null;
        Cache::forget(self::CACHE_KEY);
    }

    /* Convenience typed accessors used across the app. */

    public function ppnPercent(): float
    {
        return (float) $this->get('tax.ppn_percent', 11);
    }

    public function ppnEnabled(): bool
    {
        return (bool) $this->get('tax.enabled', true);
    }

    public function whatsappNumber(): string
    {
        return (string) $this->get('whatsapp.number', '628123456789');
    }

    public function whatsappEnabled(): bool
    {
        return (bool) $this->get('whatsapp.enabled', true);
    }

    public function company(): array
    {
        return [
            'legal_name' => $this->get('company.legal_name', config('rekasurya.company.legal_name')),
            'brand_name' => $this->get('company.brand_name', config('rekasurya.company.brand_name')),
            'npwp' => $this->get('company.npwp', config('rekasurya.company.npwp')),
            'address' => $this->get('company.address', config('rekasurya.company.address')),
            'email' => $this->get('company.email', config('rekasurya.company.email')),
            'phone' => $this->get('company.phone', config('rekasurya.company.phone')),
        ];
    }
}
