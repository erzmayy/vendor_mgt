<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Penanganan galat tak terduga: pengguna hanya melihat halaman umum dengan kode rujukan,
 * rincian teknis (pesan, berkas, trace) hanya masuk ke storage/logs/aplikasi.log.
 * Rincian tampil di layar hanya bila APP_DEBUG=1 (jangan dipakai di server bersama).
 */
final class Galat
{
    public static function pasang(): void
    {
        $debug = Env::ambil('APP_DEBUG', '0') === '1';
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
        // Argumen fungsi (mis. kata sandi database) tidak ikut tercatat di trace.
        ini_set('zend.exception_ignore_args', '1');
        set_exception_handler([self::class, 'tangani']);
    }

    public static function tangani(Throwable $e): void
    {
        $kode = strtoupper(bin2hex(random_bytes(4)));
        $baris = sprintf(
            "[%s] %s %s: %s di %s:%d\n%s\n\n",
            date('c'), $kode, get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()
        );
        @file_put_contents(BASE_PATH . '/storage/logs/aplikasi.log', $baris, FILE_APPEND | LOCK_EX);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        $debug = Env::ambil('APP_DEBUG', '0') === '1';
        echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>Terjadi kesalahan</title>'
           . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:60px auto;padding:0 16px">'
           . '<h1>Terjadi kesalahan</h1>'
           . '<p>Permintaan Anda tidak dapat diproses. Silakan coba lagi beberapa saat lagi. '
           . 'Jika masalah berlanjut, hubungi administrator dan sebutkan kode berikut:</p>'
           . '<p><strong>' . htmlspecialchars($kode, ENT_QUOTES, 'UTF-8') . '</strong></p>';
        if ($debug) {
            echo '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8') . '</pre>';
        }
        echo '</body></html>';
    }
}
