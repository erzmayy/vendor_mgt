<?php
declare(strict_types=1);

namespace App\Core;

/** Pesan satu kali tampil dan isian lama formulir (setelah validasi gagal). */
final class Flash
{
    public static function set(string $jenis, string $pesan): void
    {
        $_SESSION['_flash'][] = ['jenis' => $jenis, 'pesan' => $pesan];
    }

    /** @return array<int, array{jenis:string,pesan:string}> */
    public static function ambil(): array
    {
        $pesan = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $pesan;
    }

    public static function simpanIsian(array $data): void
    {
        $_SESSION['_isian'] = $data;
    }

    public static function ambilIsian(): array
    {
        $d = $_SESSION['_isian'] ?? [];
        unset($_SESSION['_isian']);
        return $d;
    }

    public static function simpanGalat(array $galat): void
    {
        $_SESSION['_galat_field'] = $galat;
    }

    public static function ambilGalat(): array
    {
        $d = $_SESSION['_galat_field'] ?? [];
        unset($_SESSION['_galat_field']);
        return $d;
    }
}
