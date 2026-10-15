<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Repositories\PengajuanRepository;

/**
 * Angka merah pada menu sidebar (seperti badge pada menu SIAP): jumlah pengajuan yang menunggu
 * tindakan sesuai peran pengguna. Kunci dipakai di config/menu_staf.php ('lencana').
 */
final class LencanaMenuService
{
    /** @return array<string,int> kunci => jumlah (0 bila tidak relevan bagi peran) */
    public function hitung(): array
    {
        $per = (new PengajuanRepository())->hitungPerStatus();
        $n = static fn (string $status): int => (int) ($per[$status] ?? 0);

        $jadwal = 0;
        if (Auth::punyaHak('jadwal.kelola')) {
            $jadwal = $n('dokumen_terverifikasi');   // menunggu dijadwalkan
        } elseif (Auth::punyaHak('hasil.catat')) {
            $jadwal = $n('terjadwal');               // menunggu hasil verifikasi
        }

        return [
            'periksa'   => Auth::punyaHak('dokumen.periksa') ? $n('diajukan') : 0,
            'jadwal'    => $jadwal,
            'penetapan' => Auth::punyaHak('klasifikasi.tetapkan') ? $n('ada_rekomendasi') : 0,
        ];
    }
}
