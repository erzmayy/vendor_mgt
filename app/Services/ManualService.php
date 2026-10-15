<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Teks;

/** Pencatatan pengajuan "Proses Manual" oleh petugas (simulasi proses paralel). */
final class ManualService
{
    public function __construct(
        private VendorService $vendor = new VendorService(),
        private DuplikatService $duplikat = new DuplikatService(),
    ) {
    }

    /**
     * @return array{ok:bool, galat:array<string,string>, duplikat:array, id:?int, data:array}
     */
    public function catat(array $in, bool $konfirmasiDuplikat, string $alasanDuplikat): array
    {
        [$d, $g] = $this->vendor->validasiProfil($in, 0);
        if ($g !== []) {
            return ['ok' => false, 'galat' => $g, 'duplikat' => [], 'id' => null, 'data' => $d];
        }

        $dup = $this->duplikat->cari($d);
        if ($dup !== []) {
            $alasanDuplikat = Teks::rapikan($alasanDuplikat);
            if (!$konfirmasiDuplikat) {
                return ['ok' => false, 'galat' => [], 'duplikat' => $dup, 'id' => null, 'data' => $d];
            }
            if (mb_strlen($alasanDuplikat) < 10 || mb_strlen($alasanDuplikat) > 300) {
                return ['ok' => false, 'galat' => ['alasan_duplikat' => 'Alasan tetap menyimpan wajib diisi (10-300 karakter).'],
                        'duplikat' => $dup, 'id' => null, 'data' => $d];
            }
        }

        $pelaku = Auth::pengguna();
        $pdo = Database::prototype();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO tbl_vendor (id_akun_vendor,nama_perusahaan,nama_perusahaan_normal,bentuk_badan_usaha,nib,
                                         bidang_usaha,alamat,nama_penanggung_jawab,email_perusahaan,no_telepon)
                 VALUES (NULL,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $d['nama_perusahaan'], Teks::normalisasiNama($d['nama_perusahaan']), $d['bentuk_badan_usaha'],
                $d['nib'] !== '' ? $d['nib'] : null, $d['bidang_usaha'], $d['alamat'],
                $d['nama_penanggung_jawab'], $d['email_perusahaan'], $d['no_telepon'],
            ]);
            $idVendor = (int) $pdo->lastInsertId();

            $pdo->prepare(
                "INSERT INTO tbl_pengajuan_vendor
                   (nomor_pengajuan,id_vendor,sumber_pengajuan,status_pengajuan,dicatat_oleh,tanggal_diajukan)
                 VALUES (?,?,'proses_manual','diajukan',?,NOW())"
            )->execute(['MNL-' . bin2hex(random_bytes(8)), $idVendor, (int) $pelaku['id']]);
            $idPengajuan = (int) $pdo->lastInsertId();

            $pdo->prepare('UPDATE tbl_pengajuan_vendor SET nomor_pengajuan = ? WHERE id_pengajuan = ?')
                ->execute([sprintf('VM-%s-%04d', date('Ym'), $idPengajuan), $idPengajuan]);

            $catatan = 'Dicatat manual oleh petugas';
            if ($dup !== []) {
                $catatan .= '. Kemungkinan duplikat diabaikan: ' . $alasanDuplikat;
            }
            $pdo->prepare(
                'INSERT INTO tbl_riwayat_status (id_pengajuan,status_asal,status_tujuan,tipe_pelaku,id_pelaku,catatan)
                 VALUES (?,NULL,"diajukan","staf",?,?)'
            )->execute([$idPengajuan, (int) $pelaku['id'], $catatan]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        AuditService::catat('pengajuan_manual_dicatat', ['duplikat_diabaikan' => $dup !== []], 'pengajuan', $idPengajuan, $idPengajuan);
        return ['ok' => true, 'galat' => [], 'duplikat' => [], 'id' => $idPengajuan, 'data' => $d];
    }
}
