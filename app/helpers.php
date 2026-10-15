<?php
declare(strict_types=1);

/** Escape output HTML (pencegahan XSS). Gunakan pada SEMUA data yang ditampilkan. */
function h(?string $teks): string
{
    return htmlspecialchars((string) $teks, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Alamat internal: index.php?hal=... */
function url(string $hal): string
{
    return 'index.php?hal=' . str_replace('%2F', '/', rawurlencode($hal));
}

/** Lencana status pengajuan (HTML sudah di-escape). */
function lencanaStatus(string $kode, array $konfigStatus): string
{
    $s = $konfigStatus['status'][$kode] ?? ['label' => $kode, 'warna' => 'abu'];
    return '<span class="status status-' . h($s['warna']) . '">' . h($s['label']) . '</span>';
}

function tgl(?string $waktu, string $format = 'd-m-Y H:i'): string
{
    return $waktu ? h(date($format, strtotime($waktu))) : '-';
}

/** Ikon SVG dari sprite (dekoratif: disembunyikan dari pembaca layar; teks pendamping wajib ada). */
function ikon(string $nama, string $kelas = ''): string
{
    $nama = preg_replace('/[^a-z0-9-]/', '', $nama) ?? '';
    return '<svg class="ikon' . ($kelas !== '' ? ' ' . h($kelas) : '') . '" aria-hidden="true" focusable="false"><use href="#i-' . $nama . '"/></svg>';
}

/** Inisial dari nama (maks. 2 huruf) untuk avatar. */
function inisial(string $nama): string
{
    $kata = preg_split('/\s+/u', trim($nama)) ?: [];
    $hasil = '';
    foreach (array_slice($kata, 0, 2) as $k) {
        $hasil .= mb_strtoupper(mb_substr($k, 0, 1));
    }
    return $hasil !== '' ? $hasil : '?';
}

/** Ilustrasi kategori (SVG dari app/Views/partials/ilustrasi.php); dekoratif karena selalu ada teks pendamping. */
function ilustrasi(string $slug): string
{
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    return '<svg class="ilustrasi" viewBox="0 0 96 72" aria-hidden="true" focusable="false"><use href="#il-' . $slug . '"/></svg>';
}

/** Ukuran berkas yang mudah dibaca: "< 1 KB", "230 KB", "1,4 MB". */
function ukuranBerkas(int $byte): string
{
    if ($byte < 1024) {
        return '< 1 KB';
    }
    if ($byte < 1024 * 1024) {
        return (int) round($byte / 1024) . ' KB';
    }
    return number_format($byte / 1048576, 1, ',', '.') . ' MB';
}