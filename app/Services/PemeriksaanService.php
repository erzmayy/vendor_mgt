<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Teks;
use App\Repositories\PenilaianRepository;
use App\Repositories\PengajuanRepository;
use DomainException;

/** Pemeriksaan data dan dokumen oleh Petugas Verifikasi. */
final class PemeriksaanService
{
    public function __construct(
        private PengajuanRepository $repo = new PengajuanRepository(),
        private WorkflowService $alur = new WorkflowService(),
    ) {
    }

    private function pelaku(): array
    {
        $p = Auth::pengguna();
        return ['tipe' => 'staf', 'id' => (int) $p['id'], 'peran' => $p['peran']];
    }

    private function pengajuan(int $id): array
    {
        $p = $this->repo->detail($id);
        if ($p === null) {
            throw new DomainException('Pengajuan tidak ditemukan.');
        }
        return $p;
    }

    /** Verifikator menandai jawaban vendor tidak sesuai kenyataan (poin jawaban dihitung 0). */
    public function tandaiJawaban(int $idPengajuan, int $idPertanyaan, bool $tidakSesuai, string $catatan): void
    {
        $p = $this->pengajuan($idPengajuan);
        if (!in_array($p['status_pengajuan'], ['sedang_diperiksa', 'terjadwal'], true)) {
            throw new DomainException('Jawaban hanya dapat ditandai saat pengajuan diperiksa atau menunggu verifikasi.');
        }
        $catatan = Teks::rapikan($catatan);
        if ($tidakSesuai && (mb_strlen($catatan) < 5 || mb_strlen($catatan) > 500)) {
            throw new DomainException('Catatan wajib diisi (5-500 karakter) bila jawaban ditandai tidak sesuai.');
        }
        $ubah = (new PenilaianRepository())->tandai(
            $idPengajuan, $idPertanyaan, $tidakSesuai, $tidakSesuai ? $catatan : null, (int) Auth::pengguna()['id']
        );
        if ($ubah === 0) {
            $c = Database::prototype()->prepare('SELECT 1 FROM tbl_jawaban_vendor WHERE id_pengajuan=? AND id_pertanyaan=?');
            $c->execute([$idPengajuan, $idPertanyaan]);
            if (!$c->fetchColumn()) {
                throw new DomainException('Jawaban tidak ditemukan pada pengajuan ini.');
            }
        }
        AuditService::catat('jawaban_ditandai', ['tidak_sesuai' => $tidakSesuai], 'pertanyaan', $idPertanyaan, $idPengajuan);
    }

    public function mulaiPeriksa(int $idPengajuan): void
    {
        $this->pengajuan($idPengajuan);
        $this->alur->ubahStatus($idPengajuan, 'sedang_diperiksa', $this->pelaku(), 'Pemeriksaan dimulai');
    }

    public function periksaDokumen(int $idPengajuan, int $idDokumen, string $hasil, string $catatan): void
    {
        $p = $this->pengajuan($idPengajuan);
        if ($p['status_pengajuan'] !== 'sedang_diperiksa') {
            throw new DomainException('Dokumen hanya dapat diperiksa saat pengajuan berstatus "Sedang diperiksa".');
        }
        if (!in_array($hasil, ['sesuai', 'tidak_sesuai'], true)) {
            throw new DomainException('Hasil pemeriksaan tidak valid.');
        }
        $catatan = Teks::rapikan($catatan);
        if ($hasil === 'tidak_sesuai' && mb_strlen($catatan) < 5) {
            throw new DomainException('Catatan wajib diisi (minimal 5 karakter) bila dokumen tidak sesuai.');
        }
        if (mb_strlen($catatan) > 500) {
            throw new DomainException('Catatan maksimal 500 karakter.');
        }

        $s = Database::prototype()->prepare(
            'UPDATE tbl_dokumen_vendor
                SET status_pemeriksaan = ?, catatan_pemeriksa = ?, diperiksa_oleh = ?, diperiksa_pada = NOW()
              WHERE id_dokumen = ? AND id_pengajuan = ?'
        );
        $s->execute([$hasil, $hasil === 'sesuai' ? null : $catatan, (int) Auth::pengguna()['id'], $idDokumen, $idPengajuan]);
        if ($s->rowCount() === 0) {
            // rowCount 0 juga terjadi bila nilai sama; pastikan dokumen memang milik pengajuan ini
            $c = Database::prototype()->prepare('SELECT 1 FROM tbl_dokumen_vendor WHERE id_dokumen=? AND id_pengajuan=?');
            $c->execute([$idDokumen, $idPengajuan]);
            if (!$c->fetchColumn()) {
                throw new DomainException('Dokumen tidak ditemukan pada pengajuan ini.');
            }
        }
        AuditService::catat('dokumen_diperiksa', ['hasil' => $hasil], 'dokumen', $idDokumen, $idPengajuan);
    }

    public function kembalikan(int $idPengajuan, string $catatan): void
    {
        $this->pengajuan($idPengajuan);
        $catatan = trim($catatan);
        if (mb_strlen($catatan) < 10 || mb_strlen($catatan) > 1000) {
            throw new DomainException('Catatan perbaikan wajib diisi (10-1000 karakter) agar vendor tahu yang harus diperbaiki.');
        }
        $pdo = Database::prototype();
        $pdo->beginTransaction();
        try {
            $this->alur->ubahStatus($idPengajuan, 'perlu_perbaikan', $this->pelaku(), 'Dikembalikan untuk perbaikan');
            $pdo->prepare('UPDATE tbl_pengajuan_vendor SET catatan_perbaikan = ? WHERE id_pengajuan = ?')
                ->execute([$catatan, $idPengajuan]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function verifikasiDokumen(int $idPengajuan): void
    {
        $this->pengajuan($idPengajuan);
        $dokumen = $this->repo->dokumen($idPengajuan);
        $adaSyarat = array_column($dokumen, 'id_syarat');
        foreach ($this->repo->syaratWajibAktif() as $s) {
            if (!in_array($s['id_syarat'], $adaSyarat, false)) {
                throw new DomainException('Dokumen wajib "' . $s['nama_dokumen'] . '" belum ada.');
            }
        }
        foreach ($dokumen as $d) {
            if ($d['status_pemeriksaan'] !== 'sesuai') {
                throw new DomainException('Semua dokumen harus berstatus "Sesuai" sebelum diverifikasi. Periksa: ' . $d['nama_dokumen'] . '.');
            }
        }
        $this->alur->ubahStatus($idPengajuan, 'dokumen_terverifikasi', $this->pelaku(), 'Seluruh dokumen sesuai');
    }
}
