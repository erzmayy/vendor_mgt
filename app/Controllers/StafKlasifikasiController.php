<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\View;
use App\Repositories\KlasifikasiRepository;
use App\Repositories\PengajuanRepository;
use App\Services\DokumenService;
use App\Services\KlasifikasiService;
use App\Services\ManualService;
use DomainException;

/** Hasil verifikasi, penetapan klasifikasi, TDR, penyelesaian, arsip, dan pencatatan manual. */
final class StafKlasifikasiController
{
    private function id(): int
    {
        $id = filter_var($_POST['id_pengajuan'] ?? $_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            View::galat(404, 'Pengajuan tidak ditemukan.');
        }
        return (int) $id;
    }

    private function jalankan(callable $aksi, int $id, string $sukses): never
    {
        try {
            $aksi();
            Flash::set('sukses', $sukses);
        } catch (DomainException $e) {
            Flash::set('galat', $e->getMessage());
        }
        header('Location: ' . url('staf/pengajuan/detail') . '&id=' . $id);
        exit;
    }

    public function hasil(): void
    {
        $id = $this->id();
        $this->jalankan(fn () => (new KlasifikasiService())->catatHasil($id, $_POST), $id,
            'Hasil verifikasi dicatat. Rekomendasi klasifikasi (simulasi) telah dibuat.');
    }

    public function tetapkan(): void
    {
        $id = $this->id();
        $this->jalankan(
            fn () => (new KlasifikasiService())->tetapkan($id, (string) ($_POST['label_klasifikasi'] ?? ''), (string) ($_POST['alasan'] ?? '')),
            $id, 'Klasifikasi (simulasi) ditetapkan.'
        );
    }

    public function tdr(): void
    {
        $id = $this->id();
        $this->jalankan(
            fn () => (new KlasifikasiService())->catatTdr($id, (string) ($_POST['tanggal_proses'] ?? ''), (string) ($_POST['keterangan'] ?? '')),
            $id, 'Proses TDR (simulasi) dicatat.'
        );
    }

    public function selesaikan(): void
    {
        $id = $this->id();
        $this->jalankan(fn () => (new KlasifikasiService())->selesaikan($id), $id, 'Pengajuan diselesaikan.');
    }

    public function arsipkan(): void
    {
        $id = $this->id();
        $this->jalankan(fn () => (new KlasifikasiService())->arsipkan($id), $id, 'Pengajuan diarsipkan.');
    }

    public function antrean(): void
    {
        $hasil = (new PengajuanRepository())->daftar(['status_in' => ['ada_rekomendasi', 'terklasifikasi', 'tdr_tercatat']], 1, 100);
        View::tampil('staf/penetapan', [
            'baris'  => $hasil['baris'],
            'status' => require BASE_PATH . '/config/status.php',
        ], 'Penetapan Klasifikasi');
    }

    public function arsip(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $halaman = max(1, (int) ($_GET['halaman'] ?? 1));
        $hasil = (new PengajuanRepository())->daftar(['q' => $q, 'status_in' => ['selesai', 'diarsipkan']], $halaman, 15);
        View::tampil('staf/arsip', [
            'q' => $q, 'baris' => $hasil['baris'], 'total' => $hasil['total'], 'halaman' => $halaman,
            'jumlahHalaman' => max(1, (int) ceil($hasil['total'] / 15)),
            'status' => require BASE_PATH . '/config/status.php',
        ], 'Arsip');
    }

    public function manualForm(): void
    {
        View::tampil('staf/manual', [
            'isian'     => Flash::ambilIsian(),
            'galat'     => Flash::ambilGalat(),
            'duplikat'  => $_SESSION['_duplikat'] ?? [],
            'pilihan'   => require BASE_PATH . '/config/pilihan.php',
        ], 'Catat Pengajuan Manual');
        unset($_SESSION['_duplikat']);
    }

    public function manualSimpan(): void
    {
        $hasil = (new ManualService())->catat(
            $_POST,
            ($_POST['konfirmasi_duplikat'] ?? '') === '1',
            (string) ($_POST['alasan_duplikat'] ?? '')
        );
        if ($hasil['ok']) {
            Flash::set('sukses', 'Pengajuan manual dicatat. Unggah dokumen persyaratan di halaman ini, lalu mulai pemeriksaan.');
            header('Location: ' . url('staf/pengajuan/detail') . '&id=' . (int) $hasil['id']);
            exit;
        }
        $_SESSION['_duplikat'] = $hasil['duplikat'];
        Flash::simpanIsian($_POST);
        Flash::simpanGalat($hasil['galat']);
        if ($hasil['duplikat'] !== [] && $hasil['galat'] === []) {
            Flash::set('galat', 'Ditemukan kemungkinan data vendor ganda. Periksa daftar di bawah sebelum melanjutkan.');
        } else {
            Flash::set('galat', 'Data belum tersimpan. Periksa isian yang ditandai.');
        }
        View::alihkan('staf/manual');
    }

    /** Unggah dokumen untuk pengajuan manual oleh petugas. */
    public function unggahManual(): void
    {
        $id = $this->id();
        $p = (new PengajuanRepository())->detail($id);
        if ($p === null) {
            View::galat(404, 'Pengajuan tidak ditemukan.');
        }
        if ($p['sumber_pengajuan'] !== 'proses_manual') {
            Flash::set('galat', 'Petugas hanya dapat mengunggah dokumen untuk pengajuan proses manual.');
        } elseif (!in_array($p['status_pengajuan'], ['diajukan', 'sedang_diperiksa'], true)) {
            Flash::set('galat', 'Dokumen tidak dapat diunggah pada tahap ini.');
        } else {
            [$ok, $pesan] = (new DokumenService())->unggah($p, (int) ($_POST['id_syarat'] ?? 0), $_FILES['berkas'] ?? null);
            Flash::set($ok ? 'sukses' : 'galat', $ok ? 'Dokumen berhasil diunggah.' : $pesan);
        }
        header('Location: ' . url('staf/pengajuan/detail') . '&id=' . $id);
        exit;
    }
}
