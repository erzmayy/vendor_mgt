<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Pencarian vendor yang sudah memiliki klasifikasi (penetapan oleh Petugas Berwenang).
 * Satu baris per vendor: pengajuan terbaru yang sudah ditetapkan, dengan penetapan terakhirnya.
 */
final class RekananRepository
{
    private const DASAR = "
        FROM tbl_penetapan_klasifikasi pk
        JOIN (SELECT id_pengajuan, MAX(id_penetapan) AS mx FROM tbl_penetapan_klasifikasi GROUP BY id_pengajuan) t
          ON t.mx = pk.id_penetapan
        JOIN tbl_pengajuan_vendor p ON p.id_pengajuan = pk.id_pengajuan
        JOIN tbl_vendor v ON v.id_vendor = p.id_vendor
       WHERE p.id_pengajuan = (
                SELECT MAX(p2.id_pengajuan) FROM tbl_pengajuan_vendor p2
                 WHERE p2.id_vendor = v.id_vendor
                   AND EXISTS (SELECT 1 FROM tbl_penetapan_klasifikasi x WHERE x.id_pengajuan = p2.id_pengajuan))";

    private const KOLOM = 'v.id_vendor, v.nama_perusahaan, v.bentuk_badan_usaha, v.bidang_usaha, v.email_perusahaan,
                           v.no_telepon, p.id_pengajuan, p.nomor_pengajuan, p.status_pengajuan, p.sumber_pengajuan,
                           pk.label_klasifikasi, pk.ditetapkan_pada';

    /**
     * @param array{bidang?:string,level?:string,q?:string} $f
     * @return array<int,array>
     */
    public function cari(array $f, int $batas = 500): array
    {
        [$kondisi, $par] = $this->kondisi($f);
        $s = Database::prototype()->prepare(
            'SELECT ' . self::KOLOM . self::DASAR . $kondisi
            . ' ORDER BY v.bidang_usaha, pk.label_klasifikasi, v.nama_perusahaan LIMIT ' . (int) $batas
        );
        $s->execute($par);
        return $s->fetchAll();
    }

    /** @return array<string,array<string,int>> bidang usaha => level => jumlah (tanpa filter) */
    public function matriks(): array
    {
        $baris = Database::prototype()->query(
            'SELECT v.bidang_usaha AS bidang, pk.label_klasifikasi AS level, COUNT(*) AS n ' . self::DASAR
            . ' GROUP BY v.bidang_usaha, pk.label_klasifikasi'
        )->fetchAll();
        $hasil = [];
        foreach ($baris as $b) {
            $hasil[(string) $b['bidang']][(string) $b['level']] = (int) $b['n'];
        }
        return $hasil;
    }

    /** @return string[] label level yang pernah ditetapkan */
    public function levelTersedia(): array
    {
        return Database::prototype()->query(
            'SELECT DISTINCT label_klasifikasi FROM tbl_penetapan_klasifikasi ORDER BY label_klasifikasi'
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function kondisi(array $f): array
    {
        $k = [];
        $par = [];
        if (($f['bidang'] ?? '') !== '') {
            $k[] = 'v.bidang_usaha = :bidang';
            $par[':bidang'] = $f['bidang'];
        }
        if (($f['level'] ?? '') !== '') {
            $k[] = 'pk.label_klasifikasi = :level';
            $par[':level'] = $f['level'];
        }
        if (($f['q'] ?? '') !== '') {
            $k[] = '(v.nama_perusahaan LIKE :q1 OR p.nomor_pengajuan LIKE :q2)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $par[':q1'] = $like;
            $par[':q2'] = $like;
        }
        return [$k ? ' AND ' . implode(' AND ', $k) : '', $par];
    }
}
