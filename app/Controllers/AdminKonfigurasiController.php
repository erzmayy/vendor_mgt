<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Flash;
use App\Core\View;
use App\Repositories\PenilaianRepository;
use App\Services\KonfigurasiService;
use DomainException;

/** Konfigurasi persyaratan dokumen, field formulir, aturan klasifikasi, pertanyaan, dan bobot (Administrator). */
final class AdminKonfigurasiController
{
    public const JENIS = [
        'pertanyaan' => ['judul' => 'Pertanyaan Penilaian (simulasi)', 'tabel' => null, 'urut' => null, 'metode' => 'simpanPertanyaan'],
        'bobot'  => ['judul' => 'Bobot Penilaian (simulasi)',     'tabel' => null, 'urut' => null, 'metode' => 'simpanBobot'],
        'aturan' => ['judul' => 'Aturan Klasifikasi (simulasi)', 'tabel' => 'tbl_aturan_klasifikasi', 'urut' => 'urutan, id_aturan', 'metode' => 'simpanAturan'],
        'syarat' => ['judul' => 'Persyaratan Dokumen',           'tabel' => 'tbl_syarat_dokumen',     'urut' => 'urutan, id_syarat', 'metode' => 'simpanSyarat'],
        'field'  => ['judul' => 'Field Formulir Tambahan',        'tabel' => 'tbl_field_formulir',     'urut' => 'urutan, id_field',  'metode' => 'simpanField'],
    ];

    private function jenis(): string
    {
        $j = (string) ($_GET['jenis'] ?? $_POST['jenis'] ?? '');
        if (!isset(self::JENIS[$j])) {
            View::galat(404, 'Halaman konfigurasi tidak ditemukan.');
        }
        return $j;
    }

    /**
     * Nama view dan data untuk satu jenis konfigurasi (dipakai halaman sendiri dan Pengaturan gabungan).
     * @return array{0: string, 1: array, 2: string} [view, data, judul]
     */
    public function tampilanJenis(string $j): array
    {
        $c = self::JENIS[$j];
        $data = ['jenis' => $j, 'judulJenis' => $c['judul']];

        if ($j === 'bobot') {
            return ['admin/konfigurasi_bobot', $data + ['bobot' => (new PenilaianRepository())->bobot()], $c['judul']];
        }
        if ($j === 'pertanyaan') {
            $repo = new PenilaianRepository();
            $baris = $repo->semuaPertanyaan();
            foreach ($baris as &$b) {
                $b['ada_jawaban'] = $repo->adaJawabanUntuk((int) $b['id_pertanyaan']);
            }
            unset($b);
            return ['admin/konfigurasi_pertanyaan', $data + ['baris' => $baris], $c['judul']];
        }
        // nama tabel dan urutan berasal dari konstanta di atas, bukan dari input pengguna
        $baris = Database::prototype()->query("SELECT * FROM {$c['tabel']} ORDER BY {$c['urut']}")->fetchAll();
        return ['admin/konfigurasi', $data + ['baris' => $baris, 'isian' => []], $c['judul']];
    }

    public function index(): void
    {
        [$view, $data, $judul] = $this->tampilanJenis($this->jenis());
        View::tampil($view, $data, $judul);
    }

    public function simpan(): void
    {
        $j = $this->jenis();
        try {
            (new KonfigurasiService())->{self::JENIS[$j]['metode']}($_POST);
            Flash::set('sukses', 'Konfigurasi tersimpan.');
        } catch (DomainException $e) {
            Flash::set('galat', $e->getMessage());
        }
        header('Location: ' . url('admin/pengaturan') . '&bagian=' . $j);
        exit;
    }
}
