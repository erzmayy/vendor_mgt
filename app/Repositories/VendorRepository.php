<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** Akses data tbl_vendor, tbl_pengajuan_vendor, dan jejaknya. Semua query memakai prepared statement. */
final class VendorRepository
{
    public function vendorByAkun(int $idAkun): ?array
    {
        $s = Database::prototype()->prepare('SELECT * FROM tbl_vendor WHERE id_akun_vendor = ? LIMIT 1');
        $s->execute([$idAkun]);
        return $s->fetch() ?: null;
    }

    public function buatVendor(int $idAkun, string $nama, string $normal, ?string $email): int
    {
        $pdo = Database::prototype();
        $pdo->prepare(
            'INSERT INTO tbl_vendor (id_akun_vendor,nama_perusahaan,nama_perusahaan_normal,email_perusahaan) VALUES (?,?,?,?)'
        )->execute([$idAkun, $nama, $normal, $email]);
        return (int) $pdo->lastInsertId();
    }

    public function perbaruiProfil(int $idVendor, array $d): void
    {
        Database::prototype()->prepare(
            'UPDATE tbl_vendor SET nama_perusahaan=:nama, nama_perusahaan_normal=:normal, bentuk_badan_usaha=:bentuk,
                    nib=:nib, bidang_usaha=:bidang, alamat=:alamat, nama_penanggung_jawab=:pj,
                    email_perusahaan=:email, no_telepon=:telp
              WHERE id_vendor=:id'
        )->execute([
            ':nama' => $d['nama_perusahaan'], ':normal' => $d['nama_perusahaan_normal'],
            ':bentuk' => $d['bentuk_badan_usaha'], ':nib' => $d['nib'] ?: null,
            ':bidang' => $d['bidang_usaha'], ':alamat' => $d['alamat'],
            ':pj' => $d['nama_penanggung_jawab'], ':email' => $d['email_perusahaan'],
            ':telp' => $d['no_telepon'], ':id' => $idVendor,
        ]);
    }

    public function nibDipakaiVendorLain(string $nib, int $idVendor): bool
    {
        $s = Database::prototype()->prepare('SELECT 1 FROM tbl_vendor WHERE nib = ? AND id_vendor <> ? LIMIT 1');
        $s->execute([$nib, $idVendor]);
        return (bool) $s->fetchColumn();
    }

    public function pengajuanTerbaru(int $idVendor): ?array
    {
        $s = Database::prototype()->prepare(
            'SELECT * FROM tbl_pengajuan_vendor WHERE id_vendor = ? ORDER BY id_pengajuan DESC LIMIT 1'
        );
        $s->execute([$idVendor]);
        return $s->fetch() ?: null;
    }

    public function buatPengajuan(int $idVendor, string $sumber = 'portal_vendor_management'): int
    {
        $pdo = Database::prototype();
        $sementara = 'DRF-' . bin2hex(random_bytes(8));
        $pdo->prepare(
            'INSERT INTO tbl_pengajuan_vendor (nomor_pengajuan,id_vendor,sumber_pengajuan,status_pengajuan) VALUES (?,?,?,"draf")'
        )->execute([$sementara, $idVendor, $sumber]);
        return (int) $pdo->lastInsertId();
    }

    public function setNomorPengajuan(int $idPengajuan, string $nomor): void
    {
        Database::prototype()->prepare('UPDATE tbl_pengajuan_vendor SET nomor_pengajuan = ? WHERE id_pengajuan = ?')
            ->execute([$nomor, $idPengajuan]);
    }

    public function simpanDataTambahan(int $idPengajuan, array $data): void
    {
        Database::prototype()->prepare('UPDATE tbl_pengajuan_vendor SET data_tambahan = ? WHERE id_pengajuan = ?')
            ->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $idPengajuan]);
    }

    public function riwayatStatus(int $idPengajuan): array
    {
        $s = Database::prototype()->prepare(
            'SELECT status_asal, status_tujuan, tipe_pelaku, catatan, waktu
               FROM tbl_riwayat_status WHERE id_pengajuan = ? ORDER BY waktu DESC, id_riwayat DESC'
        );
        $s->execute([$idPengajuan]);
        return $s->fetchAll();
    }

    public function jadwalAktif(int $idPengajuan): ?array
    {
        $s = Database::prototype()->prepare(
            "SELECT tanggal_verifikasi, lokasi, metode, catatan FROM tbl_jadwal_verifikasi
              WHERE id_pengajuan = ? AND status_jadwal = 'aktif' ORDER BY tanggal_verifikasi DESC LIMIT 1"
        );
        $s->execute([$idPengajuan]);
        return $s->fetch() ?: null;
    }
}
