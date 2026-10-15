<?php
declare(strict_types=1);

namespace App\Core;

final class Teks
{
    /** Nama perusahaan dinormalkan untuk pemeriksaan duplikat: huruf kecil, tanpa tanda baca, tanpa PT/CV/UD. */
    public static function normalisasiNama(string $nama): string
    {
        $nama = mb_strtolower($nama);
        $nama = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $nama) ?? '';
        $kata = array_filter(explode(' ', $nama), static fn ($k) => $k !== '' && !in_array($k, ['pt', 'cv', 'ud', 'tbk'], true));
        return implode(' ', $kata);
    }

    public static function rapikan(mixed $nilai): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $nilai) ?? '');
    }
}
