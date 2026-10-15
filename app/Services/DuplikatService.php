<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Teks;

/**
 * Pemeriksaan kemungkinan data vendor ganda. Bersifat peringatan untuk petugas,
 * kecuali NIB yang sama (diblokir oleh aturan unik database dan validasi profil).
 */
final class DuplikatService
{
    /**
     * @param array{nama_perusahaan?:string,nib?:string,email_perusahaan?:string,no_telepon?:string} $d
     * @return array<int, array{vendor:array, alasan:string[], id_pengajuan:?int}>
     */
    public function cari(array $d, int $kecualiVendor = 0): array
    {
        $nib   = strtoupper(trim((string) ($d['nib'] ?? '')));
        $nama  = Teks::normalisasiNama((string) ($d['nama_perusahaan'] ?? ''));
        $email = mb_strtolower(trim((string) ($d['email_perusahaan'] ?? '')));
        $telp  = preg_replace('/\D+/', '', (string) ($d['no_telepon'] ?? '')) ?? '';

        $kondisi = [];
        $par = [];
        if ($nib !== '')   { $kondisi[] = 'v.nib = :nib';                       $par[':nib'] = $nib; }
        if ($nama !== '')  { $kondisi[] = 'v.nama_perusahaan_normal = :nama';   $par[':nama'] = $nama; }
        if ($email !== '') { $kondisi[] = 'v.email_perusahaan = :email';        $par[':email'] = $email; }
        if (strlen($telp) >= 6) {
            $kondisi[] = "REGEXP_REPLACE(v.no_telepon, '[^0-9]', '') = :telp";
            $par[':telp'] = $telp;
        }
        if ($kondisi === []) {
            return [];
        }

        $s = Database::prototype()->prepare(
            'SELECT v.*, (SELECT MAX(p.id_pengajuan) FROM tbl_pengajuan_vendor p WHERE p.id_vendor = v.id_vendor) AS id_pengajuan_terbaru
               FROM tbl_vendor v
              WHERE v.id_vendor <> :kecuali AND (' . implode(' OR ', $kondisi) . ')
              LIMIT 10'
        );
        $s->execute($par + [':kecuali' => $kecualiVendor]);

        $hasil = [];
        foreach ($s->fetchAll() as $v) {
            $alasan = [];
            if ($nib !== '' && $v['nib'] === $nib) {
                $alasan[] = 'NIB / identitas sama';
            }
            if ($nama !== '' && $v['nama_perusahaan_normal'] === $nama) {
                $alasan[] = 'Nama perusahaan sama atau sangat mirip';
            }
            if ($email !== '' && $v['email_perusahaan'] === $email) {
                $alasan[] = 'Email perusahaan sama';
            }
            if ($telp !== '' && preg_replace('/\D+/', '', (string) $v['no_telepon']) === $telp) {
                $alasan[] = 'Nomor telepon sama';
            }
            $hasil[] = [
                'vendor' => $v,
                'alasan' => $alasan,
                'id_pengajuan' => $v['id_pengajuan_terbaru'] !== null ? (int) $v['id_pengajuan_terbaru'] : null,
            ];
        }
        return $hasil;
    }
}
