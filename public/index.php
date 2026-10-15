<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Router;
use App\Core\Session;

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; frame-ancestors 'none'");

\App\Core\Masukan::bersihkan();
Session::mulai();

$rute = require BASE_PATH . '/config/rute.php';
$hal  = (string) ($_GET['hal'] ?? 'beranda');
Router::jalankan($rute, $hal, $_SERVER['REQUEST_METHOD'] ?? 'GET');
