<?php

namespace App\Support;

/**
 * Spells a rupiah amount in Indonesian words — required wording on a kuitansi
 * ("Terbilang: …"). Cents are ignored: retail receipts here are whole rupiah.
 */
class Terbilang
{
    private const UNITS = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    public static function rupiah(float $amount): string
    {
        $words = trim(self::words((int) round(abs($amount))));

        return ucfirst($words === '' ? 'nol' : $words).' rupiah';
    }

    private static function words(int $number): string
    {
        if ($number < 12) {
            return self::UNITS[$number];
        }

        return match (true) {
            $number < 20 => self::words($number - 10).' belas',
            $number < 100 => trim(self::words(intdiv($number, 10)).' puluh '.self::words($number % 10)),
            $number < 200 => trim('seratus '.self::words($number - 100)),
            $number < 1000 => trim(self::words(intdiv($number, 100)).' ratus '.self::words($number % 100)),
            $number < 2000 => trim('seribu '.self::words($number - 1000)),
            $number < 1_000_000 => trim(self::words(intdiv($number, 1000)).' ribu '.self::words($number % 1000)),
            $number < 1_000_000_000 => trim(self::words(intdiv($number, 1_000_000)).' juta '.self::words($number % 1_000_000)),
            $number < 1_000_000_000_000 => trim(self::words(intdiv($number, 1_000_000_000)).' miliar '.self::words($number % 1_000_000_000)),
            default => trim(self::words(intdiv($number, 1_000_000_000_000)).' triliun '.self::words($number % 1_000_000_000_000)),
        };
    }
}
