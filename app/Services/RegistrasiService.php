<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Teks;
use App\Repositories\AkunVendorRepository;
use PDOException;

/** Pendaftaran akun vendor baru. */
final class RegistrasiService
{
    private const BATAS_PER_JAM_PER_IP = 10;

    public function __construct(private AkunVendorRepository $akun = new AkunVendorRepository())
    {
    }

    /**
     * @return array{0: bool, 1: array<string,string>} [berhasil, galat per field]
     */
    public function daftar(array $in): array
    {
        $nama     = Teks::rapikan($in['nama_kontak'] ?? '');
        $email    = mb_strtolower(trim((string) ($in['email'] ?? '')));
        $usaha    = Teks::rapikan($in['nama_perusahaan'] ?? '');
        $sandi    = (string) ($in['kata_sandi'] ?? '');
        $konfirm  = (string) ($in['konfirmasi_sandi'] ?? '');
        $galat    = [];

        if (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) {
            $galat['nama_kontak'] = 'Nama kontak 3-100 karakter.';
        }
        if (mb_strlen($usaha) < 3 || mb_strlen($usaha) > 150) {
            $galat['nama_perusahaan'] = 'Nama perusahaan 3-150 karakter.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
            $galat['email'] = 'Format email tidak valid.';
        }
        if (strlen($sandi) < 10 || strlen($sandi) > 72 || !preg_match('/[A-Za-z]/', $sandi) || !preg_match('/\d/', $sandi)) {
            $galat['kata_sandi'] = 'Kata sandi 10-72 karakter dan memuat huruf serta angka.';
        }
        if ($sandi !== $konfirm) {
            $galat['konfirmasi_sandi'] = 'Konfirmasi kata sandi tidak sama.';
        }
        if ($galat === [] && $this->akun->cariByEmail($email) !== null) {
            $galat['email'] = 'Email sudah terdaftar. Silakan masuk atau gunakan email lain.';
        }
        if ($galat === [] && AuditService::jumlahAksiDariIp('registrasi_vendor', 60) >= self::BATAS_PER_JAM_PER_IP) {
            $galat['umum'] = 'Terlalu banyak pendaftaran dari alamat ini. Coba lagi nanti.';
        }
        if ($galat !== []) {
            return [false, $galat];
        }

        $pdo = Database::prototype();
        try {
            $pdo->beginTransaction();
            $idAkun = $this->akun->buat($email, password_hash($sandi, PASSWORD_BCRYPT), $nama);
            (new VendorService())->pastikanProfil($idAkun, $usaha);
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() === '23000') { // email ganda pada balapan permintaan
                return [false, ['email' => 'Email sudah terdaftar. Silakan masuk atau gunakan email lain.']];
            }
            throw $e;
        }

        AuditService::catat('registrasi_vendor', ['id_akun_vendor' => $idAkun], 'akun_vendor', $idAkun);
        return [true, []];
    }
}
