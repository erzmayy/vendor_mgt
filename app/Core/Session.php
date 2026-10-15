<?php
declare(strict_types=1);

namespace App\Core;

/** Pengaturan session yang aman: cookie HttpOnly, SameSite, batas tidak aktif 30 menit. */
final class Session
{
    private const BATAS_TIDAK_AKTIF = 1800;

    public static function mulai(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name(Env::ambil('SESSION_NAME', 'vmp_sesi') ?? 'vmp_sesi');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();

        $sekarang = time();
        if (isset($_SESSION['aktivitas_terakhir']) && $sekarang - $_SESSION['aktivitas_terakhir'] > self::BATAS_TIDAK_AKTIF) {
            self::hancurkan();
            session_start();
        }
        $_SESSION['aktivitas_terakhir'] = $sekarang;
    }

    public static function gantiId(): void
    {
        session_regenerate_id(true);
    }

    public static function hancurkan(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }
}
