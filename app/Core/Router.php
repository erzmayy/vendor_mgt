<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\AuditService;

/**
 * Penegak aturan akses di backend. Urutan pemeriksaan:
 *   rute ada -> metode HTTP -> CSRF (POST) -> status login/tipe akun -> hak akses.
 */
final class Router
{
    public static function jalankan(array $rute, string $hal, string $metodeHttp): void
    {
        $r = $rute[$hal] ?? null;
        if ($r === null) {
            View::galat(404, 'Halaman tidak ditemukan.');
        }
        if ($metodeHttp !== $r['http']) {
            View::galat(405, 'Metode permintaan tidak diizinkan.');
        }
        if ($metodeHttp === 'POST' && !Csrf::periksa()) {
            AuditService::catat('csrf_ditolak', ['hal' => $hal]);
            View::galat(400, 'Sesi formulir tidak valid atau kedaluwarsa. Silakan muat ulang halaman dan coba lagi.');
        }

        // Mode sesi SIAP: pulihkan pengguna staf dari session SIAP bila belum ada.
        if (Auth::pengguna() === null && Env::ambil('SUMBER_LOGIN', 'lokal') === 'sesi_siap') {
            $siap = (new AuthService())->dariSesiSiap($_SESSION);
            if ($siap !== null) {
                Auth::masuk('staf', $siap['id'], $siap['nama'], $siap['peran'], false);
            }
        }

        $pengguna = Auth::pengguna();
        switch ($r['akses']) {
            case 'publik':
                break;
            case 'login':
                if ($pengguna === null) {
                    View::alihkan('masuk-staf');
                }
                break;
            case 'staf':
            case 'vendor':
                if ($pengguna === null) {
                    View::alihkan($r['akses'] === 'vendor' ? 'masuk-vendor' : 'masuk-staf');
                }
                if ($pengguna['tipe'] !== $r['akses']) {
                    AuditService::catat('akses_ditolak', ['hal' => $hal, 'alasan' => 'tipe_akun']);
                    View::galat(403, 'Akun Anda tidak berhak membuka halaman ini.');
                }
                break;
        }

        if (isset($r['hak']) && !Auth::punyaHak($r['hak'])) {
            AuditService::catat('akses_ditolak', ['hal' => $hal, 'hak' => $r['hak']]);
            View::galat(403, 'Peran Anda tidak memiliki hak akses untuk halaman ini.');
        }

        $kontroler = new $r['kelas']();
        $kontroler->{$r['metode']}($r);
    }
}
