<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Teks;
use App\Repositories\DokumenRepository;
use App\Repositories\FormulirRepository;
use App\Repositories\PenilaianRepository;
use App\Repositories\VendorRepository;
use DomainException;

/**
 * Aturan bisnis sisi vendor: profil, informasi tambahan, kelengkapan, pengiriman.
 * Seluruh metode menerima $idAkun dari session (BUKAN dari input pengguna),
 * sehingga vendor hanya dapat menyentuh data miliknya sendiri.
 */
final class VendorService
{
    public const STATUS_BISA_DIEDIT = ['draf', 'perlu_perbaikan'];
    private const WAJIB_PROFIL = [
        'nama_perusahaan' => 'Nama perusahaan',
        'bentuk_badan_usaha' => 'Bentuk badan usaha',
        'bidang_usaha' => 'Bidang usaha',
        'alamat' => 'Alamat perusahaan',
        'nama_penanggung_jawab' => 'Nama penanggung jawab',
        'email_perusahaan' => 'Email perusahaan',
        'no_telepon' => 'Nomor telepon',
    ];

    private array $pilihan;

    public function __construct(
        private VendorRepository $vendor = new VendorRepository(),
        private FormulirRepository $formulir = new FormulirRepository(),
        private DokumenRepository $dokumen = new DokumenRepository(),
        private WorkflowService $alur = new WorkflowService(),
        private PenilaianRepository $penilaian = new PenilaianRepository(),
    ) {
        $this->pilihan = require BASE_PATH . '/config/pilihan.php';
    }

    /** Membuat profil dan pengajuan draf bila belum ada. @return array{vendor:array,pengajuan:array} */
    public function pastikanProfil(int $idAkun, string $namaPerusahaan = ''): array
    {
        $v = $this->vendor->vendorByAkun($idAkun);
        if ($v === null) {
            $nama = $namaPerusahaan !== '' ? $namaPerusahaan : '(belum diisi)';
            $id = $this->vendor->buatVendor($idAkun, $nama, Teks::normalisasiNama($nama), null);
            $v = $this->vendor->vendorByAkun($idAkun);
            AuditService::catat('profil_vendor_dibuat', [], 'vendor', $id);
        }
        $p = $this->vendor->pengajuanTerbaru((int) $v['id_vendor']);
        if ($p === null) {
            $idP = $this->vendor->buatPengajuan((int) $v['id_vendor']);
            $p = $this->vendor->pengajuanTerbaru((int) $v['id_vendor']);
            $this->catatRiwayatAwal($idP);
        }
        return ['vendor' => $v, 'pengajuan' => $p];
    }

    private function catatRiwayatAwal(int $idPengajuan): void
    {
        \App\Core\Database::prototype()->prepare(
            'INSERT INTO tbl_riwayat_status (id_pengajuan,status_asal,status_tujuan,tipe_pelaku,id_pelaku,catatan)
             VALUES (?,NULL,"draf","sistem",NULL,"Draf pengajuan dibuat")'
        )->execute([$idPengajuan]);
    }

    public function bisaDiedit(array $pengajuan): bool
    {
        return in_array($pengajuan['status_pengajuan'], self::STATUS_BISA_DIEDIT, true);
    }

    /**
     * Validasi isian profil perusahaan. Dipakai portal vendor dan pencatatan manual petugas.
     * @return array{0: array, 1: array<string,string>} [data bersih, galat per field]
     */
    public function validasiProfil(array $in, int $idVendorKecuali): array
    {
        $d = [
            'nama_perusahaan'       => Teks::rapikan($in['nama_perusahaan'] ?? ''),
            'bentuk_badan_usaha'    => (string) ($in['bentuk_badan_usaha'] ?? ''),
            'nib'                   => strtoupper(Teks::rapikan($in['nib'] ?? '')),
            'bidang_usaha'          => (string) ($in['bidang_usaha'] ?? ''),
            'alamat'                => trim((string) ($in['alamat'] ?? '')),
            'nama_penanggung_jawab' => Teks::rapikan($in['nama_penanggung_jawab'] ?? ''),
            'email_perusahaan'      => mb_strtolower(trim((string) ($in['email_perusahaan'] ?? ''))),
            'no_telepon'            => Teks::rapikan($in['no_telepon'] ?? ''),
        ];
        $g = [];

        if (mb_strlen($d['nama_perusahaan']) < 3 || mb_strlen($d['nama_perusahaan']) > 150) {
            $g['nama_perusahaan'] = 'Nama perusahaan 3-150 karakter.';
        }
        if (!in_array($d['bentuk_badan_usaha'], $this->pilihan['bentuk_badan_usaha'], true)) {
            $g['bentuk_badan_usaha'] = 'Pilih bentuk badan usaha.';
        }
        if (!in_array($d['bidang_usaha'], $this->pilihan['bidang_usaha'], true)) {
            $g['bidang_usaha'] = 'Pilih bidang usaha.';
        }
        if ($d['nib'] !== '' && !preg_match('#^[A-Z0-9./\- ]{5,30}$#', $d['nib'])) {
            $g['nib'] = 'NIB/identitas 5-30 karakter (huruf, angka, titik, garis, spasi).';
        } elseif ($d['nib'] !== '' && $this->vendor->nibDipakaiVendorLain($d['nib'], $idVendorKecuali)) {
            $g['nib'] = 'NIB/identitas ini sudah digunakan pada pendaftaran lain.';
        }
        if (mb_strlen($d['alamat']) < 10 || mb_strlen($d['alamat']) > 500) {
            $g['alamat'] = 'Alamat 10-500 karakter.';
        }
        if (mb_strlen($d['nama_penanggung_jawab']) < 3 || mb_strlen($d['nama_penanggung_jawab']) > 100) {
            $g['nama_penanggung_jawab'] = 'Nama penanggung jawab 3-100 karakter.';
        }
        if (!filter_var($d['email_perusahaan'], FILTER_VALIDATE_EMAIL) || mb_strlen($d['email_perusahaan']) > 100) {
            $g['email_perusahaan'] = 'Format email tidak valid.';
        }
        if (!preg_match('/^[0-9+\-() ]{6,25}$/', $d['no_telepon'])) {
            $g['no_telepon'] = 'Nomor telepon 6-25 karakter (angka, +, -, spasi, tanda kurung).';
        }
        return [$d, $g];
    }

    /** @return array{0: bool, 1: array<string,string>} */
    public function simpanProfil(int $idAkun, array $in): array
    {
        ['vendor' => $v, 'pengajuan' => $p] = $this->pastikanProfil($idAkun);
        if (!$this->bisaDiedit($p)) {
            throw new DomainException('Pengajuan sudah dikirim dan tidak dapat diubah.');
        }

        [$d, $g] = $this->validasiProfil($in, (int) $v['id_vendor']);
        if ($g !== []) {
            return [false, $g];
        }

        $d['nama_perusahaan_normal'] = Teks::normalisasiNama($d['nama_perusahaan']);
        $this->vendor->perbaruiProfil((int) $v['id_vendor'], $d);
        AuditService::catat('profil_vendor_disimpan', [], 'vendor', (int) $v['id_vendor'], (int) $p['id_pengajuan']);
        return [true, []];
    }

    /** @return array{0: bool, 1: array<string,string>} */
    public function simpanTambahan(int $idAkun, array $in): array
    {
        ['pengajuan' => $p] = $this->pastikanProfil($idAkun);
        if (!$this->bisaDiedit($p)) {
            throw new DomainException('Pengajuan sudah dikirim dan tidak dapat diubah.');
        }

        $data = [];
        $g = [];
        foreach ($this->formulir->fieldAktif() as $f) {
            $kode = $f['kode_field'];
            $nilai = Teks::rapikan($in[$kode] ?? '');
            if ($f['tipe_input'] === 'area_teks') {
                $nilai = trim((string) ($in[$kode] ?? ''));
            }
            if ($nilai === '') {
                if ((int) $f['wajib'] === 1) {
                    $g[$kode] = $f['label'] . ' wajib diisi.';
                }
                continue;
            }
            switch ($f['tipe_input']) {
                case 'angka':
                    if (!ctype_digit($nilai) || strlen($nilai) > 9) {
                        $g[$kode] = $f['label'] . ' harus berupa angka.';
                        continue 2;
                    }
                    break;
                case 'tanggal':
                    $t = \DateTime::createFromFormat('Y-m-d', $nilai);
                    if (!$t || $t->format('Y-m-d') !== $nilai) {
                        $g[$kode] = $f['label'] . ' harus berformat tanggal yang benar.';
                        continue 2;
                    }
                    break;
                case 'pilihan':
                    $opsi = array_filter(array_map('trim', explode("\n", (string) $f['opsi_pilihan'])));
                    if (!in_array($nilai, $opsi, true)) {
                        $g[$kode] = 'Pilihan ' . $f['label'] . ' tidak valid.';
                        continue 2;
                    }
                    break;
                case 'area_teks':
                    if (mb_strlen($nilai) > 1000) {
                        $g[$kode] = $f['label'] . ' maksimal 1000 karakter.';
                        continue 2;
                    }
                    break;
                default:
                    if (mb_strlen($nilai) > 200) {
                        $g[$kode] = $f['label'] . ' maksimal 200 karakter.';
                        continue 2;
                    }
            }
            $data[$kode] = $nilai;
        }
        if ($g !== []) {
            return [false, $g];
        }
        $this->vendor->simpanDataTambahan((int) $p['id_pengajuan'], $data);
        AuditService::catat('data_tambahan_disimpan', [], 'pengajuan', (int) $p['id_pengajuan'], (int) $p['id_pengajuan']);
        return [true, []];
    }

    /**
     * Simpan jawaban pertanyaan penilaian (boleh sebagian, sebagai draf).
     * Kolom input: q_<id_pertanyaan> = id_opsi.
     * @return array{0: bool, 1: array<string,string>}
     */
    public function simpanJawaban(int $idAkun, array $in): array
    {
        ['pengajuan' => $p] = $this->pastikanProfil($idAkun);
        if (!$this->bisaDiedit($p)) {
            throw new DomainException('Pengajuan sudah dikirim dan tidak dapat diubah.');
        }
        $g = [];
        $simpan = [];
        foreach ($this->penilaian->pertanyaanAktif() as $q) {
            $kunci = 'q_' . $q['id_pertanyaan'];
            $nilai = $in[$kunci] ?? '';
            if (!is_string($nilai) || $nilai === '') {
                continue;
            }
            if (!ctype_digit($nilai) || !$this->penilaian->opsiMilikPertanyaan((int) $nilai, (int) $q['id_pertanyaan'])) {
                $g[$kunci] = 'Pilihan jawaban tidak valid.';
                continue;
            }
            $simpan[(int) $q['id_pertanyaan']] = (int) $nilai;
        }
        if ($g !== []) {
            return [false, $g];
        }
        foreach ($simpan as $idQ => $idOpsi) {
            $this->penilaian->simpanJawaban((int) $p['id_pengajuan'], $idQ, $idOpsi);
        }
        AuditService::catat('jawaban_penilaian_disimpan', ['jumlah' => count($simpan)], 'pengajuan', (int) $p['id_pengajuan'], (int) $p['id_pengajuan']);
        return [true, []];
    }

    /** Daftar kekurangan yang menghalangi pengiriman. @return string[] */
    public function kekurangan(array $vendor, array $pengajuan): array
    {
        $k = [];
        foreach (self::WAJIB_PROFIL as $kolom => $label) {
            $nilai = trim((string) ($vendor[$kolom] ?? ''));
            if ($nilai === '' || $nilai === '(belum diisi)') {
                $k[] = "{$label} belum diisi";
            }
        }
        $tambahan = json_decode((string) ($pengajuan['data_tambahan'] ?? '{}'), true) ?: [];
        foreach ($this->formulir->fieldAktif() as $f) {
            if ((int) $f['wajib'] === 1 && trim((string) ($tambahan[$f['kode_field']] ?? '')) === '') {
                $k[] = $f['label'] . ' belum diisi';
            }
        }
        $dijawab = $this->penilaian->jawaban((int) $pengajuan['id_pengajuan']);
        foreach ($this->penilaian->pertanyaanAktif() as $q) {
            if ((int) $q['wajib'] === 1 && !isset($dijawab[(int) $q['id_pertanyaan']])) {
                $k[] = 'Pertanyaan penilaian "' . $q['teks_pertanyaan'] . '" belum dijawab';
            }
        }
        $terunggah = $this->dokumen->dokumenPengajuan((int) $pengajuan['id_pengajuan']);
        foreach ($this->dokumen->syaratAktif() as $s) {
            $d = $terunggah[(int) $s['id_syarat']] ?? null;
            if ((int) $s['wajib'] === 1 && $d === null) {
                $k[] = 'Dokumen "' . $s['nama_dokumen'] . '" belum diunggah';
            } elseif ($d !== null && $d['status_pemeriksaan'] === 'tidak_sesuai') {
                $k[] = 'Dokumen "' . $s['nama_dokumen'] . '" perlu diganti (dinyatakan tidak sesuai oleh petugas)';
            }
        }
        return $k;
    }

    /** @return array{0: bool, 1: string[]|string} */
    public function kirim(int $idAkun): array
    {
        ['vendor' => $v, 'pengajuan' => $p] = $this->pastikanProfil($idAkun);
        if (!$this->bisaDiedit($p)) {
            return [false, 'Pengajuan sudah dikirim.'];
        }
        $kurang = $this->kekurangan($v, $p);
        if ($kurang !== []) {
            return [false, $kurang];
        }
        try {
            $this->alur->ubahStatus((int) $p['id_pengajuan'], 'diajukan', ['tipe' => 'vendor', 'id' => $idAkun, 'peran' => ['vendor']]);
        } catch (DomainException $e) {
            return [false, $e->getMessage()];
        }
        if (str_starts_with((string) $p['nomor_pengajuan'], 'DRF-')) {
            $nomor = sprintf('VM-%s-%04d', date('Ym'), (int) $p['id_pengajuan']);
            $this->vendor->setNomorPengajuan((int) $p['id_pengajuan'], $nomor);
        }
        return [true, ''];
    }
}
