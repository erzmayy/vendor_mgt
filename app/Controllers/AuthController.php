<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Env;
use App\Core\View;
use App\Services\AuditService;
use App\Services\AuthService;

final class AuthController
{
    private function loginStafLokalAktif(): void
    {
        if (Env::ambil('SUMBER_LOGIN', 'lokal') !== 'lokal') {
            View::galat(404, 'Login petugas dilakukan melalui SIAP.');
        }
    }

    public function formStaf(): void
    {
        $this->loginStafLokalAktif();
        if (Auth::pengguna() !== null) {
            View::alihkan(Auth::pengguna()['tipe'] === 'vendor' ? 'vendor/dashboard' : 'staf/dashboard');
        }
        View::tampil('masuk', [
            'jenis' => 'staf', 'aksi' => 'masuk-staf/proses', 'galat' => $_SESSION['galat_login'] ?? null,
        ], 'Masuk Petugas');
        unset($_SESSION['galat_login']);
    }

    public function prosesStaf(): void
    {
        $this->loginStafLokalAktif();
        [$ok, $pesan] = (new AuthService())->masukStaf((string) ($_POST['userid'] ?? ''), (string) ($_POST['kata_sandi'] ?? ''));
        if ($ok) {
            View::alihkan('staf/dashboard');
        }
        $_SESSION['galat_login'] = $pesan;
        View::alihkan('masuk-staf');
    }

    public function formVendor(): void
    {
        if (Auth::pengguna() !== null) {
            View::alihkan(Auth::pengguna()['tipe'] === 'vendor' ? 'vendor/dashboard' : 'staf/dashboard');
        }
        View::tampil('masuk', [
            'jenis' => 'vendor', 'aksi' => 'masuk-vendor/proses', 'galat' => $_SESSION['galat_login'] ?? null,
        ], 'Masuk Vendor');
        unset($_SESSION['galat_login']);
    }

    public function prosesVendor(): void
    {
        [$ok, $pesan] = (new AuthService())->masukVendor((string) ($_POST['email'] ?? ''), (string) ($_POST['kata_sandi'] ?? ''));
        if ($ok) {
            View::alihkan('vendor/dashboard');
        }
        $_SESSION['galat_login'] = $pesan;
        View::alihkan('masuk-vendor');
    }

    public function keluar(): void
    {
        $tipe = Auth::pengguna()['tipe'] ?? 'staf';
        AuditService::catat('logout');
        Auth::keluar();
        header('Location: ' . url($tipe === 'vendor' ? 'masuk-vendor' : 'masuk-staf'));
        exit;
    }
}
