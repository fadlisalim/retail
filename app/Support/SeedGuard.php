<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Pelindung suntingan admin untuk seeder yang dijalankan ulang lewat deploy/seeders.txt.
 *
 * Seeder mencatat nilai terakhir yang ia tulis per kolom (tabel seed_snapshots). Saat
 * jalan ulang, kolom hanya ditimpa bila nilainya di database masih sama dengan catatan
 * itu — artinya belum disunting admin. Foto/dokumen/kategori yang dulu diberikan seeder
 * lalu dihapus admin tidak dikembalikan. Singkatnya: suntingan admin selalu menang.
 */
final class SeedGuard
{
    /** @var array<string, mixed> */
    private array $values;

    public function __construct(private readonly string $key)
    {
        $row = DB::table('seed_snapshots')->where('key', $key)->value('values');
        $this->values = $row ? (array) json_decode($row, true) : [];
    }

    /** Seeder pernah menulis catatan untuk kunci ini (data pernah dibuat seeder). */
    public function exists(): bool
    {
        return $this->values !== [];
    }

    /**
     * Isi kolom ke model hanya bila belum disunting admin (model belum disimpan).
     * Tanpa catatan sebelumnya, kolom dianggap aman hanya bila masih kosong atau nilainya sudah sama.
     *
     * @param  array<string, mixed>  $desired
     * @return list<string> kolom yang diisi
     */
    public function apply(Model $model, array $desired): array
    {
        $applied = [];
        foreach ($desired as $field => $value) {
            $current = $model->getAttribute($field);
            $recorded = array_key_exists($field, $this->values);
            if (self::same($current, $recorded ? $this->values[$field] : $value) || (! $recorded && blank($current))) {
                $model->setAttribute($field, $value);
                $this->values[$field] = $value;
                $applied[] = $field;
            }
        }

        return $applied;
    }

    /**
     * Boleh menambahkan item daftar (foto/dokumen/kategori)? Ya bila belum ada dan belum
     * pernah diberikan seeder; item yang dulu diberikan lalu dihapus admin tidak kembali.
     */
    public function shouldAdd(string $list, string $item, bool $existsNow): bool
    {
        $given = (array) ($this->values['@'.$list] ?? []);
        $add = ! $existsNow && ! in_array($item, $given, true);
        if ($existsNow || $add) {
            $this->values['@'.$list] = array_values(array_unique([...$given, $item]));
        }

        return $add;
    }

    public function commit(): void
    {
        DB::table('seed_snapshots')->updateOrInsert(
            ['key' => $this->key],
            ['values' => json_encode($this->values), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    private static function same(mixed $a, mixed $b): bool
    {
        if ($a instanceof \BackedEnum) {
            $a = $a->value;
        }

        return match (true) {
            is_bool($a) || is_bool($b) => (bool) $a === (bool) $b,
            is_numeric($a) && is_numeric($b) => (float) $a === (float) $b,
            is_array($a) || is_array($b) => json_encode($a) === json_encode($b),
            default => trim((string) $a) === trim((string) $b),
        };
    }
}
