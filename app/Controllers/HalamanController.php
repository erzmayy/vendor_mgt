<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class HalamanController
{
    public function beranda(): void
    {
        View::tampil('beranda', [], 'Vendor Management PT Persona Prima Utama');
    }
}
