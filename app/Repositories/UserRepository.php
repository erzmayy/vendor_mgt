<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Env;
use PDO;

/**
 * Satu-satunya pintu ke data staf (tbl_user) dan pemetaan peran.
 * SUMBER_USER=lokal       : tbl_user di database prototype (data fiktif)
 * SUMBER_USER=siap_lintas : tbl_user dari salinan database `siap` lokal, HANYA DIBACA
 * Pemetaan peran (tbl_peran_user) selalu di database prototype.
 */
final class UserRepository
{
    private function lintas(): bool
    {
        return Env::ambil('SUMBER_USER', 'lokal') === 'siap_lintas';
    }

    private function koneksiUser(): PDO
    {
        return $this->lintas() ? Database::siap() : Database::prototype();
    }

    public function cariByUserid(string $userid): ?array
    {
        $stmt = $this->koneksiUser()->prepare(
            'SELECT id, userid, password_new, nama, dept, divisi, email FROM tbl_user WHERE userid = ? LIMIT 1'
        );
        $stmt->execute([$userid]);
        return $stmt->fetch() ?: null;
    }

    /** @return string[] peran aktif Vendor Management milik pegawai */
    public function peranAktif(int $idUser): array
    {
        $stmt = Database::prototype()->prepare(
            'SELECT peran FROM tbl_peran_user WHERE id_user = ? AND aktif = 1 ORDER BY peran'
        );
        $stmt->execute([$idUser]);
        return array_column($stmt->fetchAll(), 'peran');
    }

    public function catatLoginTerakhir(int $idUser): void
    {
        if ($this->lintas()) {
            return; // data SIAP tidak pernah ditulis oleh prototype
        }
        Database::prototype()->prepare('UPDATE tbl_user SET lastlogin = NOW() WHERE id = ?')->execute([$idUser]);
    }

    public function cariById(int $id): ?array
    {
        $stmt = $this->koneksiUser()->prepare(
            'SELECT id, userid, nama, dept, divisi, email FROM tbl_user WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @param int[] $ids @return array<int,array> kunci = id */
    public function cariBanyakById(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->koneksiUser()->prepare("SELECT id, userid, nama, dept, divisi FROM tbl_user WHERE id IN ({$in})");
        $stmt->execute($ids);
        $hasil = [];
        foreach ($stmt->fetchAll() as $b) {
            $hasil[(int) $b['id']] = $b;
        }
        return $hasil;
    }

    /** Pencarian pegawai untuk pengelolaan peran. */
    public function cari(string $kata, int $batas = 20): array
    {
        $kata = addcslashes($kata, '%_\\');
        $stmt = $this->koneksiUser()->prepare(
            'SELECT id, userid, nama, dept, divisi FROM tbl_user
              WHERE userid LIKE :q OR nama LIKE :q2 ORDER BY nama LIMIT ' . (int) $batas
        );
        $stmt->execute([':q' => "%{$kata}%", ':q2' => "%{$kata}%"]);
        return $stmt->fetchAll();
    }

    public function semuaPeran(): array
    {
        return Database::prototype()->query(
            'SELECT * FROM tbl_peran_user ORDER BY peran, userid'
        )->fetchAll();
    }

    /** @return int[] id_user pegawai berperan verifikator aktif */
    public function idVerifikatorAktif(): array
    {
        return array_map('intval', Database::prototype()->query(
            "SELECT id_user FROM tbl_peran_user WHERE peran='verifikator' AND aktif=1"
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    public function jumlahAdminAktif(): int
    {
        return (int) Database::prototype()->query(
            "SELECT COUNT(*) FROM tbl_peran_user WHERE peran='admin' AND aktif=1"
        )->fetchColumn();
    }

    public function setPeran(int $idUser, string $userid, string $peran, bool $aktif, int $oleh): void
    {
        Database::prototype()->prepare(
            'INSERT INTO tbl_peran_user (id_user,userid,peran,aktif,ditetapkan_oleh)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE aktif = VALUES(aktif), ditetapkan_oleh = VALUES(ditetapkan_oleh), ditetapkan_pada = NOW()'
        )->execute([$idUser, $userid, $peran, $aktif ? 1 : 0, $oleh]);
    }
}
