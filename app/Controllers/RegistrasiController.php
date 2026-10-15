<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\View;
use App\Services\RegistrasiService;

final class RegistrasiController
{
    public function form(): void
    {
        if (Auth::pengguna() !== null) {
            View::alihkan(Auth::pengguna()['tipe'] === 'vendor' ? 'vendor/dashboard' : 'staf/dashboard');
        }
        View::tampil('vendor/daftar', [
            'isian' => Flash::ambilIsian(),
            'galat' => Flash::ambilGalat(),
        ], 'Daftar Vendor');
    }

    public function proses(): void
    {
        $in = [
            'nama_kontak'      => $_POST['nama_kontak'] ?? '',
            'nama_perusahaan'  => $_POST['nama_perusahaan'] ?? '',
            'email'            => $_POST['email'] ?? '',
            'kata_sandi'       => $_POST['kata_sandi'] ?? '',
            'konfirmasi_sandi' => $_POST['konfirmasi_sandi'] ?? '',
        ];
        [$ok, $galat] = (new RegistrasiService())->daftar($in);
        if (!$ok) {
            unset($in['kata_sandi'], $in['konfirmasi_sandi']); // sandi tidak dikembalikan ke form
            Flash::simpanIsian($in);
            Flash::simpanGalat($galat);
            View::alihkan('daftar-vendor');
        }
        Flash::set('sukses', 'Akun vendor berhasil dibuat. Silakan masuk dengan email dan kata sandi Anda.');
        View::alihkan('masuk-vendor');
    }
}
