<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\View;
use App\Repositories\DokumenRepository;
use App\Services\AuditService;
use App\Services\DokumenService;
use App\Services\VendorService;

final class DokumenController
{
    public function unggah(): void
    {
        $svc = new VendorService();
        ['pengajuan' => $p] = $svc->pastikanProfil((int) Auth::pengguna()['id']);
        if (!$svc->bisaDiedit($p)) {
            Flash::set('galat', 'Pengajuan sudah dikirim dan dokumen tidak dapat diubah.');
            View::alihkan('vendor/dashboard');
        }
        $idSyarat = (int) ($_POST['id_syarat'] ?? 0);
        [$ok, $pesan] = (new DokumenService())->unggah($p, $idSyarat, $_FILES['berkas'] ?? null);
        Flash::set($ok ? 'sukses' : 'galat', $ok ? 'Dokumen berhasil diunggah.' : $pesan);
        header('Location: ' . url('vendor/formulir') . '&langkah=4');
        exit;
    }

    public function hapus(): void
    {
        $svc = new VendorService();
        ['pengajuan' => $p] = $svc->pastikanProfil((int) Auth::pengguna()['id']);
        if (!$svc->bisaDiedit($p)) {
            Flash::set('galat', 'Pengajuan sudah dikirim dan dokumen tidak dapat diubah.');
            View::alihkan('vendor/dashboard');
        }
        $ok = (new DokumenService())->hapus($p, (int) ($_POST['id_dokumen'] ?? 0));
        Flash::set($ok ? 'sukses' : 'galat', $ok ? 'Dokumen dihapus.' : 'Dokumen tidak ditemukan.');
        header('Location: ' . url('vendor/formulir') . '&langkah=4');
        exit;
    }

    /** Pengunduhan dokumen: vendor hanya miliknya, staf hanya dengan hak dokumen.periksa. */
    public function unduh(): void
    {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $d = $id ? (new DokumenRepository())->dokumenDenganPemilik((int) $id) : null;
        $p = Auth::pengguna();

        if ($d === null) {
            View::galat(404, 'Dokumen tidak ditemukan.');
        }
        if ($p['tipe'] === 'vendor') {
            if ((int) $d['id_akun_vendor'] !== (int) $p['id']) {
                AuditService::catat('akses_ditolak', ['hal' => 'dokumen/unduh', 'alasan' => 'bukan_pemilik'], 'dokumen', (int) $id);
                View::galat(404, 'Dokumen tidak ditemukan.');
            }
        } elseif (!Auth::punyaHak('dokumen.periksa')) {
            AuditService::catat('akses_ditolak', ['hal' => 'dokumen/unduh', 'hak' => 'dokumen.periksa'], 'dokumen', (int) $id);
            View::galat(403, 'Peran Anda tidak berhak membuka dokumen.');
        }

        $berkas = DokumenService::folderUnggah((int) $d['id_pengajuan']) . '/' . basename((string) $d['nama_file_simpan']);
        if (!is_file($berkas)) {
            View::galat(404, 'Berkas tidak ditemukan di penyimpanan.');
        }
        if ($p['tipe'] === 'staf') {
            AuditService::catat('dokumen_dilihat', [], 'dokumen', (int) $id, (int) $d['id_pengajuan']);
        }

        $namaAman = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $d['nama_file_asli']);
        header('Content-Type: ' . $d['tipe_mime']);
        header('Content-Length: ' . filesize($berkas));
        header('Content-Disposition: inline; filename="' . $namaAman . '"');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Cache-Control: private, no-store');
        readfile($berkas);
        exit;
    }
}
