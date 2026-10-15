<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Teks;
use App\Repositories\PengajuanRepository;
use App\Repositories\UserRepository;
use DateTime;
use DomainException;

/** Penjadwalan verifikasi oleh Divisi Perencanaan. */
final class JadwalService
{
    public function __construct(
        private PengajuanRepository $repo = new PengajuanRepository(),
        private UserRepository $user = new UserRepository(),
        private WorkflowService $alur = new WorkflowService(),
    ) {
    }

    /** @throws DomainException */
    public function simpan(int $idPengajuan, array $in): void
    {
        $p = $this->repo->detail($idPengajuan);
        if ($p === null) {
            throw new DomainException('Pengajuan tidak ditemukan.');
        }
        if (!in_array($p['status_pengajuan'], ['dokumen_terverifikasi', 'terjadwal'], true)) {
            throw new DomainException('Jadwal hanya dapat dibuat setelah dokumen terverifikasi dan sebelum hasil dicatat.');
        }

        $waktu = DateTime::createFromFormat('Y-m-d\TH:i', (string) ($in['tanggal_verifikasi'] ?? ''));
        if (!$waktu || $waktu->format('Y-m-d\TH:i') !== $in['tanggal_verifikasi']) {
            throw new DomainException('Tanggal dan jam verifikasi tidak valid.');
        }
        if ($waktu <= new DateTime()) {
            throw new DomainException('Jadwal verifikasi harus di waktu yang akan datang.');
        }
        $metode = (string) ($in['metode'] ?? '');
        if (!in_array($metode, ['kunjungan_lapangan', 'daring', 'dokumen'], true)) {
            throw new DomainException('Metode verifikasi tidak valid.');
        }
        $lokasi = Teks::rapikan($in['lokasi'] ?? '');
        if ($metode === 'kunjungan_lapangan' && $lokasi === '') {
            throw new DomainException('Lokasi wajib diisi untuk kunjungan lapangan.');
        }
        if (mb_strlen($lokasi) > 200) {
            throw new DomainException('Lokasi maksimal 200 karakter.');
        }
        $catatan = trim((string) ($in['catatan'] ?? ''));
        if (mb_strlen($catatan) > 500) {
            throw new DomainException('Catatan maksimal 500 karakter.');
        }
        $idVerifikator = (int) ($in['id_verifikator'] ?? 0);
        if (!in_array($idVerifikator, $this->user->idVerifikatorAktif(), true)) {
            throw new DomainException('Pilih petugas verifikasi yang valid.');
        }

        $pelaku = Auth::pengguna();
        $pdo = Database::prototype();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE tbl_jadwal_verifikasi SET status_jadwal = 'dijadwalkan_ulang'
                  WHERE id_pengajuan = ? AND status_jadwal = 'aktif'"
            )->execute([$idPengajuan]);

            $pdo->prepare(
                'INSERT INTO tbl_jadwal_verifikasi
                   (id_pengajuan,tanggal_verifikasi,lokasi,metode,id_verifikator,catatan,dibuat_oleh)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([
                $idPengajuan, $waktu->format('Y-m-d H:i:00'), $lokasi !== '' ? $lokasi : null,
                $metode, $idVerifikator, $catatan !== '' ? $catatan : null, (int) $pelaku['id'],
            ]);
            $idJadwal = (int) $pdo->lastInsertId();

            if ($p['status_pengajuan'] === 'dokumen_terverifikasi') {
                $this->alur->ubahStatus(
                    $idPengajuan, 'terjadwal',
                    ['tipe' => 'staf', 'id' => (int) $pelaku['id'], 'peran' => $pelaku['peran']],
                    'Verifikasi dijadwalkan ' . $waktu->format('d-m-Y H:i')
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        AuditService::catat(
            $p['status_pengajuan'] === 'terjadwal' ? 'jadwal_diubah' : 'jadwal_dibuat',
            ['tanggal' => $waktu->format('Y-m-d H:i'), 'metode' => $metode],
            'jadwal', $idJadwal, $idPengajuan
        );
    }
}
