<?php
declare(strict_types=1);

namespace App\Core;

/** Proteksi CSRF: token acak per session, wajib ada pada setiap permintaan POST. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function periksa(): bool
    {
        $kirim = $_POST['_csrf'] ?? '';
        return is_string($kirim) && $kirim !== '' && hash_equals(self::token(), $kirim);
    }
}
