<?php
declare(strict_types=1);

namespace App\Core;

/** Membaca konfigurasi dari berkas .env (tidak ada kredensial di kode). */
final class Env
{
    private static array $nilai = [];

    public static function muat(string $berkas): void
    {
        if (!is_file($berkas)) {
            return;
        }
        foreach (file($berkas, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $baris) {
            $baris = trim($baris);
            if ($baris === '' || $baris[0] === '#' || !str_contains($baris, '=')) {
                continue;
            }
            [$kunci, $isi] = explode('=', $baris, 2);
            self::$nilai[trim($kunci)] = trim($isi);
        }
    }

    /** Urutan: variabel lingkungan VMP_<KUNCI>, lalu berkas .env, lalu nilai bawaan. */
    public static function ambil(string $kunci, ?string $bawaan = null): ?string
    {
        $lingkungan = getenv('VMP_' . $kunci);
        if ($lingkungan !== false && $lingkungan !== '') {
            return $lingkungan;
        }
        return self::$nilai[$kunci] ?? $bawaan;
    }
}
