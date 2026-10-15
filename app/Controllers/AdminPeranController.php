<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\View;
use App\Repositories\UserRepository;
use App\Services\PeranService;
use DomainException;

/** Pemetaan manual peran Vendor Management ke pegawai. */
final class AdminPeranController
{
    /** Data tampilan peran (dipakai halaman sendiri dan halaman Pengaturan gabungan). */
    public function data(): array
    {
        $users = new UserRepository();
        $q = trim((string) ($_GET['q'] ?? ''));
        $hasilCari = $q !== '' ? $users->cari($q, 20) : [];
        $peran = $users->semuaPeran();

        return [
            'q'         => $q,
            'hasilCari' => $hasilCari,
            'peran'     => $peran,
            'nama'      => $users->cariBanyakById(array_column($peran, 'id_user')),
            'pilihan'   => PeranService::PERAN,
            'label'     => array_map(
                static fn ($p) => Auth::labelPeran($p),
                array_combine(PeranService::PERAN, PeranService::PERAN)
            ),
        ];
    }

    public function index(): void
    {
        View::tampil('admin/peran', $this->data(), 'Peran Pengguna');
    }

    public function simpan(): void
    {
        try {
            (new PeranService())->simpan(
                (int) ($_POST['id_user'] ?? 0),
                (string) ($_POST['peran'] ?? ''),
                ($_POST['aktif'] ?? '') === '1'
            );
            Flash::set('sukses', 'Peran tersimpan.');
        } catch (DomainException $e) {
            Flash::set('galat', $e->getMessage());
        }
        header('Location: ' . url('admin/pengaturan') . '&bagian=peran');
        exit;
    }
}
