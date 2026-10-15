<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Status login pada session.
 * Bentuk $_SESSION['pengguna']:
 *   ['tipe' => 'staf'|'vendor', 'id' => int, 'nama' => string, 'peran' => string[]]
 */
final class Auth
{
    private static ?array $matriks = null;

    public static function pengguna(): ?array
    {
        return $_SESSION['pengguna'] ?? null;
    }

    public static function masuk(string $tipe, int $id, string $nama, array $peran, bool $gantiId = true): void
    {
        if ($gantiId) {
            Session::gantiId(); // cegah session fixation
            unset($_SESSION['_csrf']);
        }
        $_SESSION['pengguna'] = ['tipe' => $tipe, 'id' => $id, 'nama' => $nama, 'peran' => $peran];
    }

    public static function keluar(): void
    {
        Session::hancurkan();
    }

    /** Daftar seluruh hak milik pengguna (gabungan semua perannya). */
    public static function hakPengguna(): array
    {
        $p = self::pengguna();
        if ($p === null) {
            return [];
        }
        $matriks = self::matriks();
        $hak = [];
        foreach ($p['peran'] as $peran) {
            $hak = array_merge($hak, $matriks[$peran]['hak'] ?? []);
        }
        return array_values(array_unique($hak));
    }

    public static function punyaHak(string $hak): bool
    {
        return in_array($hak, self::hakPengguna(), true);
    }

    public static function labelPeran(string $peran): string
    {
        return self::matriks()[$peran]['label'] ?? $peran;
    }

    private static function matriks(): array
    {
        return self::$matriks ??= require BASE_PATH . '/config/hak_akses.php';
    }
}
