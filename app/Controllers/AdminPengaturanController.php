<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

/**
 * Halaman Pengaturan Vendor tunggal: seluruh bagian pengaturan (peran, pertanyaan, bobot, aturan,
 * persyaratan, field) ditampilkan pada satu halaman sebagai bagian yang dapat dibuka/ditutup.
 */
final class AdminPengaturanController
{
    public function index(): void
    {
        $bagian = [];

        $peran = new AdminPeranController();
        $bagian[] = ['kode' => 'peran', 'judul' => 'Peran Pengguna',
                     'html' => View::bagian('admin/peran', $peran->data())];

        $kfg = new AdminKonfigurasiController();
        foreach (array_keys(AdminKonfigurasiController::JENIS) as $jenis) {
            [$view, $data, $judul] = $kfg->tampilanJenis($jenis);
            $bagian[] = ['kode' => $jenis, 'judul' => $judul, 'html' => View::bagian($view, $data)];
        }

        $kode = array_column($bagian, 'kode');
        $aktif = (string) ($_GET['bagian'] ?? 'peran');
        if (!in_array($aktif, $kode, true)) {
            $aktif = 'peran';
        }

        View::tampil('admin/pengaturan', ['bagian' => $bagian, 'aktif' => $aktif], 'Pengaturan Vendor');
    }
}
