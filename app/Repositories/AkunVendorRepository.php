<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class AkunVendorRepository
{
    public function cariByEmail(string $email): ?array
    {
        $stmt = Database::prototype()->prepare(
            'SELECT id_akun_vendor, email, kata_sandi, nama_kontak, status_aktif
             FROM tbl_akun_vendor WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function catatLoginTerakhir(int $id): void
    {
        Database::prototype()
            ->prepare('UPDATE tbl_akun_vendor SET terakhir_login = NOW() WHERE id_akun_vendor = ?')
            ->execute([$id]);
    }

    public function buat(string $email, string $hash, string $namaKontak): int
    {
        $pdo = Database::prototype();
        $pdo->prepare('INSERT INTO tbl_akun_vendor (email,kata_sandi,nama_kontak) VALUES (?,?,?)')
            ->execute([$email, $hash, $namaKontak]);
        return (int) $pdo->lastInsertId();
    }
}
