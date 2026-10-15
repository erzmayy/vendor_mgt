<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Normalisasi input permintaan. Aplikasi hanya memakai nilai skalar (teks) dari GET dan POST;
 * parameter berbentuk array (mis. ?hal[]=x atau q[]=a) dibuang agar tidak memicu galat tipe.
 */
final class Masukan
{
    public static function bersihkan(): void
    {
        foreach (['_GET', '_POST'] as $sumber) {
            foreach ($GLOBALS[$sumber] as $kunci => $nilai) {
                if (!is_scalar($nilai)) {
                    unset($GLOBALS[$sumber][$kunci]);
                }
            }
        }
    }
}
