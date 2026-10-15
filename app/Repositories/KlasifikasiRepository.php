<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** Data hasil verifikasi, rekomendasi, penetapan klasifikasi, dan catatan TDR. */
final class KlasifikasiRepository
{
    public function hasil(int $idPengajuan): ?array
    {
        $s = Database::prototype()->prepare(
            'SELECT * FROM tbl_hasil_verifikasi WHERE id_pengajuan = ? ORDER BY id_hasil DESC LIMIT 1'
        );
        $s->execute([$idPengajuan]);
        return $s->fetch() ?: null;
    }

    public function rekomendasi(int $idPengajuan): ?array
    {
        $s = Database::prototype()->prepare(
            'SELECT * FROM tbl_rekomendasi_klasifikasi WHERE id_pengajuan = ? ORDER BY id_rekomendasi DESC LIMIT 1'
        );
        $s->execute([$idPengajuan]);
        return $s->fetch() ?: null;
    }

    /** Seluruh penetapan (riwayat), terbaru dahulu. */
    public function penetapan(int $idPengajuan): array
    {
        $s = Database::prototype()->prepare(
            'SELECT * FROM tbl_penetapan_klasifikasi WHERE id_pengajuan = ? ORDER BY id_penetapan DESC'
        );
        $s->execute([$idPengajuan]);
        return $s->fetchAll();
    }

    public function tdr(int $idPengajuan): array
    {
        $s = Database::prototype()->prepare(
            'SELECT * FROM tbl_catatan_tdr WHERE id_pengajuan = ? ORDER BY id_tdr DESC'
        );
        $s->execute([$idPengajuan]);
        return $s->fetchAll();
    }

    public function aturanAktif(): array
    {
        return Database::prototype()->query(
            'SELECT * FROM tbl_aturan_klasifikasi WHERE aktif = 1 ORDER BY urutan, id_aturan'
        )->fetchAll();
    }

    public function semuaAturan(): array
    {
        return Database::prototype()->query(
            'SELECT * FROM tbl_aturan_klasifikasi ORDER BY urutan, id_aturan'
        )->fetchAll();
    }
}
