<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class DokumenRepository
{
    public function syaratAktif(): array
    {
        return Database::prototype()->query(
            'SELECT * FROM tbl_syarat_dokumen WHERE aktif = 1 ORDER BY urutan, id_syarat'
        )->fetchAll();
    }

    public function syaratById(int $idSyarat): ?array
    {
        $s = Database::prototype()->prepare('SELECT * FROM tbl_syarat_dokumen WHERE id_syarat = ? AND aktif = 1');
        $s->execute([$idSyarat]);
        return $s->fetch() ?: null;
    }

    /** @return array<int, array> dokumen terunggah, kunci = id_syarat */
    public function dokumenPengajuan(int $idPengajuan): array
    {
        $s = Database::prototype()->prepare('SELECT * FROM tbl_dokumen_vendor WHERE id_pengajuan = ?');
        $s->execute([$idPengajuan]);
        $hasil = [];
        foreach ($s->fetchAll() as $baris) {
            $hasil[(int) $baris['id_syarat']] = $baris;
        }
        return $hasil;
    }

    /** Dokumen beserta pemilik (id_akun_vendor) untuk pemeriksaan kepemilikan. */
    public function dokumenDenganPemilik(int $idDokumen): ?array
    {
        $s = Database::prototype()->prepare(
            'SELECT d.*, v.id_akun_vendor
               FROM tbl_dokumen_vendor d
               JOIN tbl_pengajuan_vendor p ON p.id_pengajuan = d.id_pengajuan
               JOIN tbl_vendor v ON v.id_vendor = p.id_vendor
              WHERE d.id_dokumen = ?'
        );
        $s->execute([$idDokumen]);
        return $s->fetch() ?: null;
    }

    public function simpan(int $idPengajuan, int $idSyarat, string $asli, string $simpan, string $mime, int $ukuran): ?string
    {
        $pdo = Database::prototype();
        $lama = $pdo->prepare('SELECT nama_file_simpan FROM tbl_dokumen_vendor WHERE id_pengajuan = ? AND id_syarat = ?');
        $lama->execute([$idPengajuan, $idSyarat]);
        $berkasLama = $lama->fetchColumn() ?: null;

        $pdo->prepare(
            'INSERT INTO tbl_dokumen_vendor (id_pengajuan,id_syarat,nama_file_asli,nama_file_simpan,tipe_mime,ukuran_byte)
             VALUES (:p,:s,:a,:f,:m,:u)
             ON DUPLICATE KEY UPDATE nama_file_asli=VALUES(nama_file_asli), nama_file_simpan=VALUES(nama_file_simpan),
                 tipe_mime=VALUES(tipe_mime), ukuran_byte=VALUES(ukuran_byte),
                 status_pemeriksaan="belum_diperiksa", catatan_pemeriksa=NULL, diperiksa_oleh=NULL,
                 diperiksa_pada=NULL, diunggah_pada=NOW()'
        )->execute([':p' => $idPengajuan, ':s' => $idSyarat, ':a' => $asli, ':f' => $simpan, ':m' => $mime, ':u' => $ukuran]);

        return $berkasLama;
    }

    public function hapus(int $idDokumen): void
    {
        Database::prototype()->prepare('DELETE FROM tbl_dokumen_vendor WHERE id_dokumen = ?')->execute([$idDokumen]);
    }
}
