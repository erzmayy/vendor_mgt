<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Repositories\KlasifikasiRepository;
use App\Repositories\RekananRepository;
use App\Services\AuditService;

/** Pencarian rekanan/calon vendor per kategori (bidang usaha) dan level klasifikasi. */
final class StafRekananController
{
    /** Filter dari query string, divalidasi terhadap daftar yang dikenal. */
    private function filter(RekananRepository $repo): array
    {
        $pilihan = require BASE_PATH . '/config/pilihan.php';
        $bidang = (string) ($_GET['bidang'] ?? '');
        if (!in_array($bidang, $pilihan['bidang_usaha'], true)) {
            $bidang = '';
        }
        $level = (string) ($_GET['level'] ?? '');
        if (!in_array($level, $this->levelDikenal($repo), true)) {
            $level = '';
        }
        return ['bidang' => $bidang, 'level' => $level, 'q' => trim((string) ($_GET['q'] ?? ''))];
    }

    /** @return string[] */
    private function levelDikenal(RekananRepository $repo): array
    {
        $dariAturan = array_column((new KlasifikasiRepository())->semuaAturan(), 'label_klasifikasi');
        return array_values(array_unique(array_merge($dariAturan, $repo->levelTersedia())));
    }

    public function cari(): void
    {
        $repo = new RekananRepository();
        $f = $this->filter($repo);
        View::tampil('staf/rekanan', [
            'filter'  => $f,
            'hasil'   => $repo->cari($f),
            'matriks' => $repo->matriks(),
            'level'   => $this->levelDikenal($repo),
            'pilihan' => require BASE_PATH . '/config/pilihan.php',
            'status'  => require BASE_PATH . '/config/status.php',
        ], 'Cari Rekanan per Kategori dan Level');
    }

    public function ekspor(): void
    {
        $repo = new RekananRepository();
        $f = $this->filter($repo);
        $baris = $repo->cari($f, 5000);
        AuditService::catat('rekanan_diekspor', ['jumlah' => count($baris), 'filter' => $f]);

        // Sel yang diawali = + - @ diberi tanda kutip agar tidak dijalankan sebagai rumus oleh aplikasi spreadsheet.
        $aman = static function (?string $v): string {
            $v = (string) $v;
            return $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v;
        };

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="rekanan-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: private, no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
        fputcsv($out, ['Nama perusahaan', 'Bentuk badan usaha', 'Bidang usaha', 'Level klasifikasi (simulasi)', 'Nomor pengajuan',
                       'Status pengajuan', 'Ditetapkan', 'Email', 'Telepon']);
        foreach ($baris as $b) {
            fputcsv($out, [
                $aman($b['nama_perusahaan']), $aman($b['bentuk_badan_usaha']), $aman($b['bidang_usaha']),
                $aman($b['label_klasifikasi']), $aman($b['nomor_pengajuan']), $aman($b['status_pengajuan']),
                $aman($b['ditetapkan_pada']), $aman($b['email_perusahaan']), $aman($b['no_telepon']),
            ]);
        }
        fclose($out);
        exit;
    }
}
