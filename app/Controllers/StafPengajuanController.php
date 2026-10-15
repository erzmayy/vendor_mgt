<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\View;
use App\Repositories\PengajuanRepository;
use App\Repositories\UserRepository;
use App\Services\JadwalService;
use App\Services\PemeriksaanService;
use DomainException;

/** Layar petugas atas pengajuan vendor. Hak akses sudah dicek Router per rute. */
final class StafPengajuanController
{
    private const PER_HALAMAN = 15;

    private function idPengajuan(): int
    {
        $id = filter_var($_POST['id_pengajuan'] ?? $_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            View::galat(404, 'Pengajuan tidak ditemukan.');
        }
        return (int) $id;
    }

    /** Rincian penilaian: salinan tersimpan bila rekomendasi sudah ada, selain itu pratinjau dari data saat ini. */
    private function penilaian(int $id, \App\Repositories\KlasifikasiRepository $kl): array
    {
        $rek = $kl->rekomendasi($id);
        if ($rek && !empty($rek['rincian'])) {
            $r = json_decode((string) $rek['rincian'], true);
            if (is_array($r)) {
                return ['total' => $r['total'] ?? null, 'komponen' => $r['komponen'] ?? [], 'tersimpan' => true,
                        'jawaban' => (new \App\Repositories\PenilaianRepository())->jawaban($id)];
            }
        }
        $h = (new \App\Services\PenilaianService())->hitung($id, null);
        $h['tersimpan'] = false;
        $h['jawaban'] = (new \App\Repositories\PenilaianRepository())->jawaban($id);
        return $h;
    }

    private function kembaliKeDetail(int $id): never
    {
        header('Location: ' . url('staf/pengajuan/detail') . '&id=' . $id);
        exit;
    }

    public function daftar(): void
    {
        $status = (string) ($_GET['status'] ?? '');
        $sumber = (string) ($_GET['sumber'] ?? '');
        $daftarStatus = require BASE_PATH . '/config/status.php';
        if (!isset($daftarStatus['status'][$status]) || $status === 'draf') {
            $status = '';
        }
        if (!in_array($sumber, ['portal_vendor_management', 'proses_manual'], true)) {
            $sumber = '';
        }
        $f = ['q' => trim((string) ($_GET['q'] ?? '')), 'status' => $status, 'sumber' => $sumber];
        $halaman = max(1, (int) ($_GET['halaman'] ?? 1));
        $hasil = (new PengajuanRepository())->daftar($f, $halaman, self::PER_HALAMAN);

        View::tampil('staf/pengajuan_daftar', [
            'filter'  => $f,
            'baris'   => $hasil['baris'],
            'total'   => $hasil['total'],
            'halaman' => $halaman,
            'jumlahHalaman' => max(1, (int) ceil($hasil['total'] / self::PER_HALAMAN)),
            'status'  => $daftarStatus,
        ], 'Daftar Pengajuan');
    }

    public function detail(): void
    {
        $id = $this->idPengajuan();
        $repo = new PengajuanRepository();
        $p = $repo->detail($id);
        if ($p === null) {
            View::galat(404, 'Pengajuan tidak ditemukan.');
        }
        $users = new UserRepository();
        $kl = new \App\Repositories\KlasifikasiRepository();
        $jadwal = $repo->jadwal($id);
        $riwayat = $repo->riwayat($id);

        $ids = array_filter(array_merge(
            array_column($jadwal, 'id_verifikator'),
            array_column($kl->penetapan($id), 'ditetapkan_oleh'),
            array_column($kl->tdr($id), 'dicatat_oleh'),
            array_filter([$kl->hasil($id)['dicatat_oleh'] ?? null]),
            array_map(static fn ($r) => $r['tipe_pelaku'] === 'staf' ? $r['id_pelaku'] : null, $riwayat)
        ));
        $nama = $users->cariBanyakById($ids);

        $verifikator = [];
        if (Auth::punyaHak('jadwal.kelola')) {
            $verifikator = $users->cariBanyakById($users->idVerifikatorAktif());
        }

        View::tampil('staf/pengajuan_detail', [
            'p'           => $p,
            'dokumen'     => $repo->dokumen($id),
            'jadwal'      => $jadwal,
            'riwayat'     => $riwayat,
            'nama'        => $nama,
            'verifikator' => $verifikator,
            'fields'      => (new \App\Repositories\FormulirRepository())->fieldAktif(),
            'tambahan'    => json_decode((string) ($p['data_tambahan'] ?? '{}'), true) ?: [],
            'status'      => require BASE_PATH . '/config/status.php',
            'bisa'        => [
                'periksa'   => Auth::punyaHak('dokumen.periksa'),
                'kembalikan' => Auth::punyaHak('pengajuan.kembalikan'),
                'jadwal'    => Auth::punyaHak('jadwal.kelola'),
                'hasil'     => Auth::punyaHak('hasil.catat'),
                'rekomendasi' => Auth::punyaHak('rekomendasi.lihat'),
                'tetapkan'  => Auth::punyaHak('klasifikasi.tetapkan'),
                'tdr'       => Auth::punyaHak('tdr.catat'),
                'selesai'   => Auth::punyaHak('pengajuan.selesaikan'),
                'manual'    => Auth::punyaHak('pengajuan_manual.catat'),
            ],
            'hasil'       => $kl->hasil($id),
            'penilaian'   => $this->penilaian($id, $kl),
            'rekomendasi' => $kl->rekomendasi($id),
            'penetapan'   => $kl->penetapan($id),
            'tdr'         => $kl->tdr($id),
            'aturan'      => $kl->aturanAktif(),
            'duplikat'    => (new \App\Services\DuplikatService())->cari($p, (int) $p['id_vendor']),
            'syarat'      => (new \App\Repositories\DokumenRepository())->syaratAktif(),
        ], 'Detail Pengajuan');
    }

    private function jalankan(callable $aksi, int $id, string $pesanSukses): never
    {
        try {
            $aksi();
            Flash::set('sukses', $pesanSukses);
        } catch (DomainException $e) {
            Flash::set('galat', $e->getMessage());
        }
        $this->kembaliKeDetail($id);
    }

    public function mulaiPeriksa(): void
    {
        $id = $this->idPengajuan();
        $this->jalankan(fn () => (new PemeriksaanService())->mulaiPeriksa($id), $id, 'Pemeriksaan dimulai.');
    }

    public function periksaDokumen(): void
    {
        $id = $this->idPengajuan();
        $dok = (int) ($_POST['id_dokumen'] ?? 0);
        $hasil = (string) ($_POST['hasil'] ?? '');
        $catatan = (string) ($_POST['catatan'] ?? '');
        $this->jalankan(fn () => (new PemeriksaanService())->periksaDokumen($id, $dok, $hasil, $catatan), $id, 'Hasil pemeriksaan dokumen tersimpan.');
    }

    public function tandaiJawaban(): void
    {
        $id = $this->idPengajuan();
        $pertanyaan = (int) ($_POST['id_pertanyaan'] ?? 0);
        $tidakSesuai = ($_POST['tindakan'] ?? '') === 'tandai';
        $catatan = (string) ($_POST['catatan'] ?? '');
        $this->jalankan(
            fn () => (new PemeriksaanService())->tandaiJawaban($id, $pertanyaan, $tidakSesuai, $catatan),
            $id, $tidakSesuai ? 'Jawaban ditandai tidak sesuai kenyataan.' : 'Tanda pada jawaban dihapus.'
        );
    }

    public function kembalikan(): void
    {
        $id = $this->idPengajuan();
        $catatan = (string) ($_POST['catatan'] ?? '');
        $this->jalankan(fn () => (new PemeriksaanService())->kembalikan($id, $catatan), $id, 'Pengajuan dikembalikan ke vendor untuk diperbaiki.');
    }

    public function verifikasiDokumen(): void
    {
        $id = $this->idPengajuan();
        $this->jalankan(fn () => (new PemeriksaanService())->verifikasiDokumen($id), $id, 'Dokumen terverifikasi. Pengajuan siap dijadwalkan.');
    }

    public function jadwal(): void
    {
        $repo = new PengajuanRepository();
        $baris = $repo->jadwalAktifDaftar();
        $siap = $repo->daftar(['status' => 'dokumen_terverifikasi'], 1, 50);
        $users = new UserRepository();
        View::tampil('staf/jadwal', [
            'jadwal'  => $baris,
            'menunggu' => $siap['baris'],
            'nama'    => $users->cariBanyakById(array_column($baris, 'id_verifikator')),
            'status'  => require BASE_PATH . '/config/status.php',
            'bisaKelola' => Auth::punyaHak('jadwal.kelola'),
        ], 'Jadwal Verifikasi');
    }

    public function jadwalSimpan(): void
    {
        $id = $this->idPengajuan();
        $this->jalankan(fn () => (new JadwalService())->simpan($id, $_POST), $id, 'Jadwal verifikasi tersimpan.');
    }
}
