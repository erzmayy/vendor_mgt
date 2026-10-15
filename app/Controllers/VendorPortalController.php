<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\View;
use App\Repositories\DokumenRepository;
use App\Repositories\FormulirRepository;
use App\Repositories\PenilaianRepository;
use App\Repositories\VendorRepository;
use App\Services\VendorService;
use DomainException;

/** Portal vendor. ID akun selalu dari session, tidak pernah dari input. */
final class VendorPortalController
{
    private function idAkun(): int
    {
        return (int) Auth::pengguna()['id'];
    }

    public function dashboard(): void
    {
        $svc = new VendorService();
        $repo = new VendorRepository();
        ['vendor' => $v, 'pengajuan' => $p] = $svc->pastikanProfil($this->idAkun());
        $idP = (int) $p['id_pengajuan'];
        $dok = new DokumenRepository();

        View::tampil('vendor/dashboard', [
            'vendor'     => $v,
            'pengajuan'  => $p,
            'status'     => require BASE_PATH . '/config/status.php',
            'riwayat'    => $repo->riwayatStatus($idP),
            'jadwal'     => $repo->jadwalAktif($idP),
            'syarat'     => $dok->syaratAktif(),
            'dokumen'    => $dok->dokumenPengajuan($idP),
            'kekurangan' => $svc->kekurangan($v, $p),
            'bisaEdit'   => $svc->bisaDiedit($p),
        ], 'Dashboard Vendor');
    }

    public function formulir(): void
    {
        $svc = new VendorService();
        ['vendor' => $v, 'pengajuan' => $p] = $svc->pastikanProfil($this->idAkun());
        $langkah = (int) ($_GET['langkah'] ?? 1);
        $langkah = max(1, min(5, $langkah));
        $dok = new DokumenRepository();

        View::tampil('vendor/formulir', [
            'langkah'    => $langkah,
            'vendor'     => $v,
            'pengajuan'  => $p,
            'tambahan'   => json_decode((string) ($p['data_tambahan'] ?? '{}'), true) ?: [],
            'fields'     => (new FormulirRepository())->fieldAktif(),
            'pilihan'    => require BASE_PATH . '/config/pilihan.php',
            'syarat'     => $dok->syaratAktif(),
            'dokumen'    => $dok->dokumenPengajuan((int) $p['id_pengajuan']),
            'pertanyaan' => (new PenilaianRepository())->pertanyaanAktif(),
            'jawaban'    => (new PenilaianRepository())->jawaban((int) $p['id_pengajuan']),
            'kekurangan' => $svc->kekurangan($v, $p),
            'bisaEdit'   => $svc->bisaDiedit($p),
            'isian'      => Flash::ambilIsian(),
            'galat'      => Flash::ambilGalat(),
        ], 'Formulir Pendaftaran');
    }

    public function simpan(): void
    {
        $langkah = (int) ($_POST['langkah'] ?? 0);
        $svc = new VendorService();
        try {
            if ($langkah === 1) {
                [$ok, $galat] = $svc->simpanProfil($this->idAkun(), $_POST);
            } elseif ($langkah === 2) {
                [$ok, $galat] = $svc->simpanTambahan($this->idAkun(), $_POST);
            } elseif ($langkah === 3) {
                [$ok, $galat] = $svc->simpanJawaban($this->idAkun(), $_POST);
            } else {
                View::galat(400, 'Langkah formulir tidak valid.');
            }
        } catch (DomainException $e) {
            Flash::set('galat', $e->getMessage());
            View::alihkan('vendor/dashboard');
        }

        if (!$ok) {
            Flash::simpanIsian($_POST);
            Flash::simpanGalat($galat);
            Flash::set('galat', 'Data belum tersimpan. Periksa isian yang ditandai.');
            header('Location: ' . url('vendor/formulir') . '&langkah=' . $langkah);
            exit;
        }

        Flash::set('sukses', 'Data tersimpan sebagai draf.');
        $tujuan = isset($_POST['simpan_lanjut']) ? $langkah + 1 : $langkah;
        header('Location: ' . url('vendor/formulir') . '&langkah=' . $tujuan);
        exit;
    }

    public function kirim(): void
    {
        [$ok, $hasil] = (new VendorService())->kirim($this->idAkun());
        if ($ok) {
            Flash::set('sukses', 'Pengajuan berhasil dikirim dan masuk antrean pemeriksaan petugas.');
        } elseif (is_array($hasil)) {
            Flash::set('galat', 'Pengajuan belum dapat dikirim: ' . implode('; ', $hasil) . '.');
        } else {
            Flash::set('galat', (string) $hasil);
        }
        View::alihkan('vendor/dashboard');
    }
}
