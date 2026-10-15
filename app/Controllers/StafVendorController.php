<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Repositories\KlasifikasiRepository;
use App\Repositories\PengajuanRepository;
use App\Repositories\RekananRepository;

/**
 * Halaman Vendor Management tunggal. Dashboard (ringkasan) adalah tab pertamanya; tab lain berisi
 * seluruh pengajuan dan rekanan pada satu tabel yang dapat disaring per tahap, kategori, level,
 * sumber, dan kata kunci.
 */
final class StafVendorController
{
    /** Kelompok tahap pada tab: kode => [label, status yang tercakup (null = semua)] */
    public const SEGMEN = [
        'semua'     => ['Semua pengajuan', null],
        'periksa'   => ['Perlu diperiksa', ['diajukan', 'sedang_diperiksa', 'perlu_perbaikan']],
        'jadwal'    => ['Verifikasi', ['dokumen_terverifikasi', 'terjadwal']],
        'penetapan' => ['Klasifikasi dan TDR', ['terverifikasi', 'ada_rekomendasi', 'terklasifikasi', 'tdr_tercatat']],
        'selesai'   => ['Selesai dan arsip', ['selesai', 'diarsipkan']],
    ];

    private const PER_HALAMAN = 15;

    /** Rute staf/dashboard: membuka tab Dashboard. */
    public function dashboard(): void
    {
        $_GET['segmen'] = 'dashboard';
        $this->index();
    }

    public function index(): void
    {
        $segmen = (string) ($_GET['segmen'] ?? 'dashboard');
        if ($segmen !== 'dashboard' && !isset(self::SEGMEN[$segmen])) {
            $segmen = 'dashboard';
        }

        $repo = new PengajuanRepository();
        $status = require BASE_PATH . '/config/status.php';
        $perStatus = $repo->hitungPerStatus();

        // jumlah per tab dihitung dari jumlah per status
        $jumlahSegmen = [];
        foreach (self::SEGMEN as $kode => [, $daftar]) {
            $jumlahSegmen[$kode] = $daftar === null
                ? array_sum($perStatus)
                : array_sum(array_map(static fn ($s) => (int) ($perStatus[$s] ?? 0), $daftar));
        }

        $data = [
            'segmen'       => $segmen,
            'segmenDaftar' => self::SEGMEN,
            'jumlahSegmen' => $jumlahSegmen,
            'status'       => $status,
            'bisaManual'   => Auth::punyaHak('pengajuan_manual.catat'),
        ];

        if ($segmen === 'dashboard') {
            View::tampil('staf/vendor', $data + $this->dataRingkasan($repo, $perStatus, $status), 'Dashboard Vendor Management');
            return;
        }
        View::tampil('staf/vendor', $data + $this->dataDaftar($repo, $segmen, $status), 'Vendor Management');
    }

    /** @return array<string,mixed> */
    private function dataRingkasan(PengajuanRepository $repo, array $per, array $status): array
    {
        $n = static fn (string ...$st): int => array_sum(array_map(static fn ($s) => (int) ($per[$s] ?? 0), $st));
        return [
            'kpi' => [
                ['label' => 'Perlu diperiksa',      'nilai' => $n('diajukan'),                         'status' => 'diajukan'],
                ['label' => 'Sedang diperiksa',     'nilai' => $n('sedang_diperiksa'),                 'status' => 'sedang_diperiksa'],
                ['label' => 'Menunggu perbaikan',   'nilai' => $n('perlu_perbaikan'),                  'status' => 'perlu_perbaikan'],
                ['label' => 'Menunggu jadwal',      'nilai' => $n('dokumen_terverifikasi'),            'status' => 'dokumen_terverifikasi'],
                ['label' => 'Terjadwal',            'nilai' => $n('terjadwal'),                        'status' => 'terjadwal'],
                ['label' => 'Proses penetapan',     'nilai' => $n('terverifikasi', 'ada_rekomendasi'), 'status' => null],
                ['label' => 'Terklasifikasi / TDR', 'nilai' => $n('terklasifikasi', 'tdr_tercatat'),   'status' => null],
                ['label' => 'Selesai / arsip',      'nilai' => $n('selesai', 'diarsipkan'),            'status' => null],
            ],
            'total'   => array_sum($per),
            'terbaru' => $repo->daftar([], 1, 8)['baris'],
            'jadwal'  => array_slice($repo->jadwalAktifDaftar(), 0, 5),
            'peran'   => array_map([Auth::class, 'labelPeran'], Auth::pengguna()['peran']),
        ];
    }

    /** @return array<string,mixed> */
    private function dataDaftar(PengajuanRepository $repo, string $segmen, array $status): array
    {
        $pilihan = require BASE_PATH . '/config/pilihan.php';
        $rekanan = new RekananRepository();

        $bidang = (string) ($_GET['bidang'] ?? '');
        if (!in_array($bidang, $pilihan['bidang_usaha'], true)) {
            $bidang = '';
        }
        $levelDikenal = array_values(array_unique(array_merge(
            array_column((new KlasifikasiRepository())->semuaAturan(), 'label_klasifikasi'),
            $rekanan->levelTersedia()
        )));
        $level = (string) ($_GET['level'] ?? '');
        if (!in_array($level, $levelDikenal, true)) {
            $level = '';
        }
        $sumber = (string) ($_GET['sumber'] ?? '');
        if (!in_array($sumber, ['portal_vendor_management', 'proses_manual'], true)) {
            $sumber = '';
        }
        $st = (string) ($_GET['status'] ?? '');
        if (!isset($status['status'][$st]) || $st === 'draf') {
            $st = '';
        }

        $f = [
            'q' => trim((string) ($_GET['q'] ?? '')), 'bidang' => $bidang, 'level' => $level,
            'sumber' => $sumber, 'status' => $st, 'status_in' => self::SEGMEN[$segmen][1] ?? [],
        ];
        $halaman = max(1, (int) ($_GET['halaman'] ?? 1));
        $hasil = $repo->daftarGabungan($f, $halaman, self::PER_HALAMAN);

        return [
            'filter'        => ['q' => $f['q'], 'bidang' => $bidang, 'level' => $level, 'sumber' => $sumber, 'status' => $st, 'segmen' => $segmen],
            'baris'         => $hasil['baris'],
            'total'         => $hasil['total'],
            'halaman'       => $halaman,
            'jumlahHalaman' => max(1, (int) ceil($hasil['total'] / self::PER_HALAMAN)),
            'level'         => $levelDikenal,
            'matriks'       => $rekanan->matriks(),
            'pilihan'       => $pilihan,
        ];
    }
}
