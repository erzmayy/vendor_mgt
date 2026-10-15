<?php
declare(strict_types=1);

// Autoload sederhana: namespace App\Foo\Bar -> app/Foo/Bar.php
spl_autoload_register(static function (string $kelas): void {
    $awalan = 'App\\';
    if (strncmp($kelas, $awalan, strlen($awalan)) !== 0) {
        return;
    }
    $berkas = __DIR__ . '/' . str_replace('\\', '/', substr($kelas, strlen($awalan))) . '.php';
    if (is_file($berkas)) {
        require $berkas;
    }
});

define('BASE_PATH', dirname(__DIR__));

require __DIR__ . '/helpers.php';

\App\Core\Env::muat(BASE_PATH . '/.env');

\App\Core\Galat::pasang();

date_default_timezone_set('Asia/Jakarta');
mb_internal_encoding('UTF-8');
