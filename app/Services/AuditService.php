<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/** Pencatatan audit log dan penghitungan percobaan login gagal. */
final class AuditService
{
    public static function catat(string $aksi, array $detail = [], ?string $objek = null, ?int $idObjek = null, ?int $idPengajuan = null): void
    {
        $p = Auth::pengguna();
        $tipe = $p['tipe'] ?? 'sistem';
        $idPelaku = $p['id'] ?? null;

        $stmt = Database::prototype()->prepare(
            'INSERT INTO tbl_log_aktivitas (tipe_pelaku,id_pelaku,aksi,objek,id_objek,id_pengajuan,detail,alamat_ip)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $tipe, $idPelaku, $aksi, $objek, $idObjek, $idPengajuan,
            $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    /** Jumlah aksi tertentu dari alamat IP pemanggil dalam N menit terakhir. */
    public static function jumlahAksiDariIp(string $aksi, int $menit): int
    {
        $stmt = Database::prototype()->prepare(
            "SELECT COUNT(*) FROM tbl_log_aktivitas
              WHERE aksi = ? AND alamat_ip = ? AND waktu > (NOW() - INTERVAL {$menit} MINUTE)"
        );
        $stmt->execute([$aksi, $_SERVER['REMOTE_ADDR'] ?? '']);
        return (int) $stmt->fetchColumn();
    }

    /** Jumlah login gagal untuk identitas tertentu dalam N menit terakhir. */
    public static function jumlahLoginGagal(string $identitas, int $menit = 15): int
    {
        $stmt = Database::prototype()->prepare(
            "SELECT COUNT(*) FROM tbl_log_aktivitas
             WHERE aksi = 'login_gagal'
               AND waktu > (NOW() - INTERVAL {$menit} MINUTE)
               AND JSON_UNQUOTE(JSON_EXTRACT(detail, '$.identitas')) = ?"
        );
        $stmt->execute([$identitas]);
        return (int) $stmt->fetchColumn();
    }
}
