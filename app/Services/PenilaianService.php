<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DokumenRepository;
use App\Repositories\PenilaianRepository;

/**
 * Perhitungan skor klasifikasi otomatis (SIMULASI, parameter dari konfigurasi):
 *   A. kelengkapan berkas   : dokumen wajib dan opsional yang dinyatakan Sesuai
 *   B. pertanyaan penilaian : poin jawaban vendor dikali bobot pertanyaan
 *   C. hasil verifikasi     : rata-rata tiga nilai petugas (baru ada setelah verifikasi dicatat)
 * Skor total = rata-rata tertimbang komponen yang tersedia; bobot dinormalkan ulang
 * bila ada komponen yang belum tersedia atau berbobot 0.
 * Bobot dan batas level BUKAN ketentuan resmi PPU.
 */
final class PenilaianService
{
    /** Porsi skor berkas: dokumen wajib 85, dokumen opsional 15 (bila ada dokumen opsional). */
    private const PORSI_WAJIB = 85.0;
    private const PORSI_OPSIONAL = 15.0;

    public function __construct(
        private PenilaianRepository $repo = new PenilaianRepository(),
        private DokumenRepository $dokumen = new DokumenRepository(),
    ) {
    }

    /** @return array{skor:float, detail:string} */
    public function skorBerkas(int $idPengajuan): array
    {
        $terunggah = $this->dokumen->dokumenPengajuan($idPengajuan);
        $wajib = $opsional = $wajibOk = $opsionalOk = 0;
        foreach ($this->dokumen->syaratAktif() as $s) {
            $ok = isset($terunggah[(int) $s['id_syarat']])
                && $terunggah[(int) $s['id_syarat']]['status_pemeriksaan'] === 'sesuai';
            if ((int) $s['wajib'] === 1) {
                $wajib++;
                $wajibOk += $ok ? 1 : 0;
            } else {
                $opsional++;
                $opsionalOk += $ok ? 1 : 0;
            }
        }
        if ($wajib === 0 && $opsional === 0) {
            return ['skor' => 100.0, 'detail' => 'Tidak ada persyaratan dokumen aktif.'];
        }
        if ($opsional === 0) {
            $skor = $wajib > 0 ? $wajibOk / $wajib * 100 : 100.0;
        } elseif ($wajib === 0) {
            $skor = $opsionalOk / $opsional * 100;
        } else {
            $skor = $wajibOk / $wajib * self::PORSI_WAJIB + $opsionalOk / $opsional * self::PORSI_OPSIONAL;
        }
        $detail = "{$wajibOk} dari {$wajib} dokumen wajib sesuai";
        if ($opsional > 0) {
            $detail .= "; {$opsionalOk} dari {$opsional} dokumen opsional sesuai";
        }
        return ['skor' => round($skor, 2), 'detail' => $detail];
    }

    /** @return array{skor:?float, detail:string, butir:array} skor null bila tidak ada pertanyaan aktif */
    public function skorPertanyaan(int $idPengajuan): array
    {
        $jawaban = $this->repo->jawaban($idPengajuan);
        $jumlahBobot = 0;
        $jumlah = 0.0;
        $butir = [];
        foreach ($this->repo->pertanyaanAktif() as $q) {
            $j = $jawaban[(int) $q['id_pertanyaan']] ?? null;
            $ditandai = $j && (int) $j['ditandai_tidak_sesuai'] === 1;
            $poin = $j && !$ditandai ? (int) $j['poin'] : 0;
            $bobot = (int) $q['bobot'];
            $jumlahBobot += $bobot;
            $jumlah += $bobot * $poin;
            $butir[] = [
                'id_pertanyaan' => (int) $q['id_pertanyaan'],
                'pertanyaan' => $q['teks_pertanyaan'], 'jawaban' => $j['teks_opsi'] ?? '(belum dijawab)',
                'poin' => $poin, 'bobot' => $bobot, 'ditandai' => $ditandai,
                'catatan' => $j['catatan_verifikator'] ?? null,
            ];
        }
        if ($jumlahBobot === 0) {
            return ['skor' => null, 'detail' => 'Tidak ada pertanyaan penilaian aktif.', 'butir' => []];
        }
        $ditandaiN = count(array_filter($butir, static fn ($b) => $b['ditandai']));
        $detail = count($butir) . ' pertanyaan' . ($ditandaiN > 0 ? "; {$ditandaiN} jawaban ditandai tidak sesuai kenyataan (poin 0)" : '');
        return ['skor' => round($jumlah / $jumlahBobot, 2), 'detail' => $detail, 'butir' => $butir];
    }

    /**
     * @param ?array{0:int,1:int,2:int} $verifikasi tiga nilai petugas, null bila belum dicatat
     * @return array{total:?float, komponen:array<string,array>}
     */
    public function hitung(int $idPengajuan, ?array $verifikasi = null): array
    {
        $bobot = $this->repo->bobot();
        $berkas = $this->skorBerkas($idPengajuan);
        $pert = $this->skorPertanyaan($idPengajuan);

        $komponen = [
            'berkas' => ['label' => 'Kelengkapan berkas', 'skor' => $berkas['skor'], 'bobot' => $bobot['bobot_berkas'], 'detail' => $berkas['detail']],
            'pertanyaan' => ['label' => 'Pertanyaan penilaian', 'skor' => $pert['skor'], 'bobot' => $bobot['bobot_pertanyaan'],
                             'detail' => $pert['detail'], 'butir' => $pert['butir']],
            'verifikasi' => [
                'label' => 'Hasil verifikasi petugas',
                'skor' => $verifikasi !== null ? round(array_sum($verifikasi) / 3, 2) : null,
                'bobot' => $bobot['bobot_verifikasi'],
                'detail' => $verifikasi !== null ? 'Rata-rata tiga nilai verifikasi' : 'Belum dicatat',
            ],
        ];

        $pembilang = 0.0;
        $penyebut = 0;
        foreach ($komponen as &$k) {
            $dipakai = $k['skor'] !== null && $k['bobot'] > 0;
            $k['dipakai'] = $dipakai;
            if ($dipakai) {
                $pembilang += $k['skor'] * $k['bobot'];
                $penyebut += $k['bobot'];
            }
        }
        unset($k);

        return [
            'total' => $penyebut > 0 ? round($pembilang / $penyebut, 2) : null,
            'komponen' => $komponen,
        ];
    }
}
