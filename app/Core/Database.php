<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Pembuat koneksi PDO. Semua query aplikasi harus memakai prepared statement.
 *  - prototype(): database prototype (baca/tulis)
 *  - siap():      salinan database SIAP lokal, hanya untuk mode siap_lintas (read-only)
 */
final class Database
{
    private static array $koneksi = [];

    public static function prototype(): PDO
    {
        return self::$koneksi['prototype'] ??= self::buat(
            Env::ambil('DB_HOST', '127.0.0.1'),
            Env::ambil('DB_PORT', '3306'),
            Env::ambil('DB_NAMA', 'vendor_management_prototype'),
            Env::ambil('DB_USER', 'root'),
            Env::ambil('DB_PASS', '')
        );
    }

    public static function siap(): PDO
    {
        return self::$koneksi['siap'] ??= self::buat(
            Env::ambil('DB_SIAP_HOST', '127.0.0.1'),
            Env::ambil('DB_SIAP_PORT', '3306'),
            Env::ambil('DB_SIAP_NAMA', 'siap'),
            Env::ambil('DB_SIAP_USER', ''),
            Env::ambil('DB_SIAP_PASS', '')
        );
    }

    private static function buat(string $host, string $port, string $nama, string $user, string $pass): PDO
    {
        $dsn = "mysql:host={$host};port={$port};dbname={$nama};charset=utf8mb4";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
}
