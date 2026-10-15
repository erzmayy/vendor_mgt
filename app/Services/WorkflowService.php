<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DomainException;

/**
 * Satu-satunya tempat aturan perpindahan status pengajuan.
 * Setiap perpindahan: dicek (status asal -> tujuan, peran), dicatat di
 * tbl_riwayat_status dan tbl_log_aktivitas, dalam satu transaksi.
 * Administrator sengaja tidak memiliki hak mengubah status.
 */
final class WorkflowService
{
    /** status asal => [status tujuan => peran yang diizinkan] */
    public const TRANSISI = [
        'draf'                  => ['diajukan' => ['vendor']],
        'perlu_perbaikan'       => ['diajukan' => ['vendor']],
        'diajukan'              => ['sedang_diperiksa' => ['verifikator']],
        'sedang_diperiksa'      => ['perlu_perbaikan' => ['verifikator'], 'dokumen_terverifikasi' => ['verifikator']],
        'dokumen_terverifikasi' => ['terjadwal' => ['perencanaan']],
        'terjadwal'             => ['terverifikasi' => ['verifikator']],
        'terverifikasi'         => ['ada_rekomendasi' => ['sistem']],
        'ada_rekomendasi'       => ['terklasifikasi' => ['berwenang']],
        'terklasifikasi'        => ['tdr_tercatat' => ['berwenang']],
        'tdr_tercatat'          => ['selesai' => ['berwenang']],
        'selesai'               => ['diarsipkan' => ['berwenang']],
    ];

    /**
     * @param array{tipe:string,id:?int,peran:string[]} $pelaku tipe: staf | vendor | sistem
     */
    public static function boleh(string $dari, string $ke, array $pelaku): bool
    {
        $diizinkan = self::TRANSISI[$dari][$ke] ?? null;
        if ($diizinkan === null) {
            return false;
        }
        $peranPelaku = $pelaku['tipe'] === 'sistem' ? ['sistem'] : $pelaku['peran'];
        return array_intersect($diizinkan, $peranPelaku) !== [];
    }

    /** @throws DomainException bila perpindahan tidak diperbolehkan */
    public function ubahStatus(int $idPengajuan, string $ke, array $pelaku, ?string $catatan = null): void
    {
        $pdo = Database::prototype();
        // Boleh dipanggil di dalam transaksi pemanggil (mis. penjadwalan).
        $mandiri = !$pdo->inTransaction();
        if ($mandiri) {
            $pdo->beginTransaction();
        }
        try {
            $stmt = $pdo->prepare('SELECT status_pengajuan FROM tbl_pengajuan_vendor WHERE id_pengajuan = ? FOR UPDATE');
            $stmt->execute([$idPengajuan]);
            $dari = $stmt->fetchColumn();
            if ($dari === false) {
                throw new DomainException('Pengajuan tidak ditemukan.');
            }
            if (!isset(self::TRANSISI[$dari][$ke])) {
                throw new DomainException('Perpindahan status tidak diperbolehkan pada tahap ini.');
            }
            if (!self::boleh($dari, $ke, $pelaku)) {
                throw new DomainException('Peran Anda tidak berwenang melakukan perpindahan status ini.');
            }

            $pdo->prepare(
                'UPDATE tbl_pengajuan_vendor
                    SET status_pengajuan = :ke,
                        tanggal_diajukan = IF(:ke2 = "diajukan", NOW(), tanggal_diajukan),
                        jumlah_pengembalian = jumlah_pengembalian + IF(:ke3 = "perlu_perbaikan", 1, 0)
                  WHERE id_pengajuan = :id'
            )->execute([':ke' => $ke, ':ke2' => $ke, ':ke3' => $ke, ':id' => $idPengajuan]);

            $pdo->prepare(
                'INSERT INTO tbl_riwayat_status (id_pengajuan,status_asal,status_tujuan,tipe_pelaku,id_pelaku,catatan)
                 VALUES (?,?,?,?,?,?)'
            )->execute([$idPengajuan, $dari, $ke, $pelaku['tipe'], $pelaku['id'], $catatan]);

            if ($mandiri) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($mandiri && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        AuditService::catat('status_diubah', ['dari' => $dari, 'ke' => $ke], 'pengajuan', $idPengajuan, $idPengajuan);
    }
}
