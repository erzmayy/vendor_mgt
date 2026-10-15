<?php
declare(strict_types=1);

namespace App\Core;

/** Menampilkan view di dalam tata letak. View tidak boleh berisi logika bisnis. */
final class View
{
    public static function tampil(string $tampilan, array $data = [], string $judul = 'Prototype Vendor Management'): void
    {
        $konten = self::muat('/app/Views/' . $tampilan . '.php', $data);
        $pengguna = Auth::pengguna();
        // Petugas memakai layout sidebar (modul di SIAP); vendor dan publik memakai layout portal.
        // Halaman publik (beranda, masuk, daftar) selalu memakai layout portal.
        $publik = in_array($tampilan, ['beranda', 'masuk', 'vendor/daftar'], true);
        $layout = !$publik && ($pengguna['tipe'] ?? '') === 'staf' ? 'tata_letak_staf' : 'tata_letak';
        echo self::muat('/app/Views/' . $layout . '.php', [
            'konten' => $konten, 'judul' => $judul, 'pengguna' => $pengguna,
        ]);
    }

    /** Merender view tanpa tata letak (untuk disusun menjadi bagian halaman lain). */
    public static function bagian(string $tampilan, array $data = []): string
    {
        return self::muat('/app/Views/' . $tampilan . '.php', $data);
    }

    /**
     * Mengeksekusi berkas view dengan variabel dari $__data.
     * Nama parameter berawalan "__" agar tidak bertabrakan dengan data view
     * (mis. variabel bernama $nama atau $data milik view).
     */
    private static function muat(string $__berkas, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        require BASE_PATH . $__berkas;
        return (string) ob_get_clean();
    }

    public static function alihkan(string $hal): void
    {
        header('Location: ' . url($hal));
        exit;
    }

    public static function galat(int $kode, string $pesan): void
    {
        http_response_code($kode);
        self::tampil('galat', ['kode' => $kode, 'pesan' => $pesan], "Galat {$kode}");
        exit;
    }
}
