<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Env;
use Throwable;

/** Halaman diagnosa lingkungan lokal. Hanya aktif bila APP_ENV=lokal. */
final class UjiKoneksiController
{
    public function tampil(): void
    {
        if (Env::ambil('APP_ENV') !== 'lokal') {
            http_response_code(404);
            echo 'Tidak ditemukan.';
            return;
        }

        $pemeriksaan = [];
        $tabel = [];
        $galat = null;

        foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'json'] as $ekstensi) {
            $pemeriksaan[] = ["Ekstensi PHP {$ekstensi}", extension_loaded($ekstensi)];
        }
        $pemeriksaan[] = ['PHP versi ' . PHP_VERSION . ' (minimal 8.0)', PHP_VERSION_ID >= 80000];
        $pemeriksaan[] = ['Folder storage/uploads dapat ditulis', is_writable(BASE_PATH . '/storage/uploads')];
        $pemeriksaan[] = ['Folder storage/logs dapat ditulis', is_writable(BASE_PATH . '/storage/logs')];

        try {
            $pdo = Database::prototype();
            $pemeriksaan[] = ['Koneksi database prototype', true];
            $nama = $pdo->query('SELECT DATABASE()')->fetchColumn();
            $daftar = $pdo->query(
                "SELECT table_name AS nama FROM information_schema.tables
                 WHERE table_schema = DATABASE() ORDER BY table_name"
            )->fetchAll();
            foreach ($daftar as $baris) {
                $n = $baris['nama'];
                $jumlah = $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $n) . '`')->fetchColumn();
                $tabel[] = [$n, (int) $jumlah];
            }
            $pemeriksaan[] = ["Database aktif: {$nama}, jumlah tabel: " . count($tabel) . ' (diharapkan 16)', count($tabel) === 16];
        } catch (Throwable $e) {
            $pemeriksaan[] = ['Koneksi database prototype', false];
            $galat = $e->getMessage();
        }

        $sumberUser = Env::ambil('SUMBER_USER', 'lokal');
        require BASE_PATH . '/app/Views/uji_koneksi.php';
    }
}
