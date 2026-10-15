<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Repositories\UserRepository;
use DomainException;

/** Pemetaan manual peran Vendor Management ke pegawai (oleh Administrator). */
final class PeranService
{
    public const PERAN = ['perencanaan', 'verifikator', 'berwenang', 'admin'];

    public function __construct(private UserRepository $user = new UserRepository())
    {
    }

    public function simpan(int $idUser, string $peran, bool $aktif): void
    {
        if (!in_array($peran, self::PERAN, true)) {
            throw new DomainException('Peran tidak valid.');
        }
        $u = $this->user->cariById($idUser);
        if ($u === null) {
            throw new DomainException('Pegawai tidak ditemukan.');
        }
        if ($peran === 'admin' && !$aktif) {
            $sudahAdmin = false;
            foreach ($this->user->semuaPeran() as $r) {
                if ((int) $r['id_user'] === $idUser && $r['peran'] === 'admin' && (int) $r['aktif'] === 1) {
                    $sudahAdmin = true;
                }
            }
            if ($sudahAdmin && $this->user->jumlahAdminAktif() <= 1) {
                throw new DomainException('Administrator aktif terakhir tidak boleh dinonaktifkan.');
            }
        }
        $this->user->setPeran($idUser, (string) $u['userid'], $peran, $aktif, (int) Auth::pengguna()['id']);
        AuditService::catat('peran_diubah', [
            'userid' => $u['userid'], 'peran' => $peran, 'aktif' => $aktif,
        ], 'user', $idUser);
    }
}
