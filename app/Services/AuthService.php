<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Repositories\AkunVendorRepository;
use App\Repositories\UserRepository;

/**
 * Aturan bisnis login. Pesan kegagalan sengaja seragam agar tidak membocorkan
 * apakah akun ada atau tidak.
 */
final class AuthService
{
    private const BATAS_GAGAL = 5;
    private const PESAN_GAGAL = 'Identitas atau kata sandi salah.';

    /** Hash tiruan agar waktu respons tidak membedakan akun ada/tidak. */
    private const HASH_TIRUAN = '$2y$10$P6ol0NxQfWgoZAPPr7WkZeW6KxyxBuuP2rSEkE6YABdmRcIBylHAC';

    public function __construct(
        private UserRepository $user = new UserRepository(),
        private AkunVendorRepository $vendor = new AkunVendorRepository(),
    ) {
    }

    /** @return array{0: bool, 1: string} */
    public function masukStaf(string $userid, string $kataSandi): array
    {
        $userid = trim($userid);
        if ($userid === '' || $kataSandi === '') {
            return [false, 'Userid dan kata sandi wajib diisi.'];
        }
        if (AuditService::jumlahLoginGagal('staf:' . $userid) >= self::BATAS_GAGAL) {
            return [false, 'Terlalu banyak percobaan gagal. Coba lagi dalam 15 menit.'];
        }

        $baris = $this->user->cariByUserid($userid);
        $hash = (string) ($baris['password_new'] ?? '');
        // Hanya bcrypt yang diterima. Hash lama (MD5) ditolak.
        $hashValid = str_starts_with($hash, '$2y$');
        $cocok = password_verify($kataSandi, $hashValid ? $hash : self::HASH_TIRUAN);

        if ($baris === null || !$hashValid || !$cocok) {
            AuditService::catat('login_gagal', [
                'identitas' => 'staf:' . $userid,
                'sebab'     => $baris === null ? 'akun_tidak_ada' : (!$hashValid ? 'hash_bukan_bcrypt' : 'sandi_salah'),
            ]);
            return [false, self::PESAN_GAGAL];
        }

        $peran = $this->user->peranAktif((int) $baris['id']);
        if ($peran === []) {
            AuditService::catat('login_gagal', ['identitas' => 'staf:' . $userid, 'sebab' => 'tanpa_peran']);
            return [false, 'Akun Anda belum memiliki akses Vendor Management. Hubungi Administrator.'];
        }

        Auth::masuk('staf', (int) $baris['id'], (string) $baris['nama'], $peran);
        $this->user->catatLoginTerakhir((int) $baris['id']);
        AuditService::catat('login_berhasil', ['peran' => $peran]);
        return [true, ''];
    }

    /**
     * Mode SUMBER_LOGIN=sesi_siap: staf sudah login di SIAP. Modul hanya membaca user_id
     * dari session, memuat pegawainya, lalu mencari peran aktifnya. Tidak ada kata sandi diperiksa.
     * Kunci session `user_id` mengikuti pola project CRF dan BELUM dikonfirmasi pada SIAP.
     *
     * @return array{id:int,nama:string,peran:string[]}|null null bila tidak ada sesi, pegawai, atau peran
     */
    public function dariSesiSiap(array $sesi): ?array
    {
        $id = filter_var($sesi['user_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            return null;
        }
        $u = $this->user->cariById((int) $id);
        if ($u === null) {
            return null;
        }
        $peran = $this->user->peranAktif((int) $u['id']);
        if ($peran === []) {
            return null;
        }
        return ['id' => (int) $u['id'], 'nama' => (string) $u['nama'], 'peran' => $peran];
    }

    /** @return array{0: bool, 1: string} */
    public function masukVendor(string $email, string $kataSandi): array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || $kataSandi === '') {
            return [false, 'Email dan kata sandi wajib diisi.'];
        }
        if (AuditService::jumlahLoginGagal('vendor:' . $email) >= self::BATAS_GAGAL) {
            return [false, 'Terlalu banyak percobaan gagal. Coba lagi dalam 15 menit.'];
        }

        $baris = $this->vendor->cariByEmail($email);
        $cocok = password_verify($kataSandi, $baris['kata_sandi'] ?? self::HASH_TIRUAN);

        if ($baris === null || !$cocok || (int) $baris['status_aktif'] !== 1) {
            AuditService::catat('login_gagal', ['identitas' => 'vendor:' . $email]);
            return [false, self::PESAN_GAGAL];
        }

        Auth::masuk('vendor', (int) $baris['id_akun_vendor'], (string) $baris['nama_kontak'], ['vendor']);
        $this->vendor->catatLoginTerakhir((int) $baris['id_akun_vendor']);
        AuditService::catat('login_berhasil');
        return [true, ''];
    }
}
