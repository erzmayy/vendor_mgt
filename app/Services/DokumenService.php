<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DokumenRepository;

/**
 * Unggah dokumen yang aman:
 *  - tipe diperiksa dari ISI berkas (finfo + tanda tangan), bukan dari nama atau header klien
 *  - hanya ekstensi dalam daftar putih per persyaratan
 *  - nama simpan acak, disimpan di storage/ (di luar folder publik)
 */
final class DokumenService
{
    private const PETA_MIME = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    ];

    public function __construct(private DokumenRepository $repo = new DokumenRepository())
    {
    }

    public static function folderUnggah(int $idPengajuan): string
    {
        return BASE_PATH . '/storage/uploads/' . $idPengajuan;
    }

    /** @return array{0: bool, 1: string} */
    public function unggah(array $pengajuan, int $idSyarat, ?array $berkas): array
    {
        $syarat = $this->repo->syaratById($idSyarat);
        if ($syarat === null) {
            return [false, 'Persyaratan dokumen tidak ditemukan.'];
        }
        if ($berkas === null || !isset($berkas['error']) || is_array($berkas['error']) || is_array($berkas['tmp_name'] ?? null)) {
            return [false, 'Pilih berkas yang akan diunggah.'];
        }
        if ($berkas['error'] === UPLOAD_ERR_INI_SIZE || $berkas['error'] === UPLOAD_ERR_FORM_SIZE) {
            return [false, 'Ukuran berkas melebihi batas.'];
        }
        if ($berkas['error'] === UPLOAD_ERR_NO_FILE) {
            return [false, 'Pilih berkas yang akan diunggah.'];
        }
        if ($berkas['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($berkas['tmp_name'])) {
            return [false, 'Unggah gagal. Silakan coba lagi.'];
        }

        $ukuran = (int) $berkas['size'];
        $batas = (int) $syarat['ukuran_maks_kb'] * 1024;
        if ($ukuran <= 0 || $ukuran > $batas) {
            return [false, 'Ukuran berkas harus di bawah ' . (int) $syarat['ukuran_maks_kb'] . ' KB.'];
        }

        $ekstensi = strtolower(pathinfo((string) $berkas['name'], PATHINFO_EXTENSION));
        $diizinkan = array_values(array_intersect(
            array_map('trim', explode(',', strtolower((string) $syarat['format_diizinkan']))),
            array_keys(self::PETA_MIME)
        ));
        if (!in_array($ekstensi, $diizinkan, true)) {
            return [false, 'Format berkas tidak diizinkan. Gunakan: ' . implode(', ', $diizinkan) . '.'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($berkas['tmp_name']);
        if ($mime !== self::PETA_MIME[$ekstensi]) {
            return [false, 'Isi berkas tidak sesuai dengan format yang dinyatakan.'];
        }
        if ($ekstensi === 'pdf') {
            $kepala = (string) file_get_contents($berkas['tmp_name'], false, null, 0, 5);
            if ($kepala !== '%PDF-') {
                return [false, 'Berkas PDF tidak valid.'];
            }
        } elseif (@getimagesize($berkas['tmp_name']) === false) {
            return [false, 'Berkas gambar tidak valid.'];
        }

        $idP = (int) $pengajuan['id_pengajuan'];
        $folder = self::folderUnggah($idP);
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            return [false, 'Penyimpanan tidak tersedia.'];
        }
        $namaSimpan = bin2hex(random_bytes(16)) . '.' . $ekstensi;
        if (!move_uploaded_file($berkas['tmp_name'], $folder . '/' . $namaSimpan)) {
            return [false, 'Berkas gagal disimpan.'];
        }

        $namaAsli = mb_substr(preg_replace('/[^\p{L}\p{N}._\- ()]/u', '_', basename((string) $berkas['name'])) ?? 'berkas', 0, 200);
        $lama = $this->repo->simpan($idP, $idSyarat, $namaAsli, $namaSimpan, $mime, $ukuran);
        if ($lama !== null && $lama !== $namaSimpan) {
            @unlink($folder . '/' . basename($lama));
        }

        AuditService::catat('dokumen_diunggah', ['syarat' => $syarat['kode_syarat'], 'ukuran' => $ukuran], 'dokumen', null, $idP);
        return [true, ''];
    }

    public function hapus(array $pengajuan, int $idDokumen): bool
    {
        $d = $this->repo->dokumenDenganPemilik($idDokumen);
        if ($d === null || (int) $d['id_pengajuan'] !== (int) $pengajuan['id_pengajuan']) {
            return false;
        }
        $this->repo->hapus($idDokumen);
        @unlink(self::folderUnggah((int) $d['id_pengajuan']) . '/' . basename((string) $d['nama_file_simpan']));
        AuditService::catat('dokumen_dihapus', [], 'dokumen', $idDokumen, (int) $d['id_pengajuan']);
        return true;
    }
}
