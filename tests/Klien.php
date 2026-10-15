<?php
declare(strict_types=1);

/** Klien HTTP sederhana untuk uji otomatis (cookie session per instance, tidak mengikuti redirect). */
final class Klien
{
    private string $jar;

    public function __construct(private string $dasar)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'vmp');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    /**
     * @param array<string,string|CURLFile> $data
     * @return array{kode:int, lokasi:string, isi:string, header:string}
     */
    public function minta(string $hal, string $metode = 'GET', array $data = [], string $tambahanQuery = ''): array
    {
        $url = $this->dasar . '/index.php?hal=' . str_replace('%2F', '/', rawurlencode($hal)) . $tambahanQuery;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_TIMEOUT        => 20,
        ]);
        if ($metode === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $adaBerkas = (bool) array_filter($data, static fn ($v) => $v instanceof CURLFile);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $adaBerkas ? $data : http_build_query($data));
        }
        $resp = (string) curl_exec($ch);
        $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ukuranHeader = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $header = substr($resp, 0, $ukuranHeader);
        $lokasi = preg_match('/^Location:\s*(.+)$/mi', $header, $m) ? trim($m[1]) : '';
        $isi = substr($resp, $ukuranHeader);
        if (preg_match('/(Fatal error|Warning|Notice|Deprecated):\s/', $isi) && str_contains($isi, ' on line ')) {
            fwrite(STDERR, "  [PERINGATAN] Galat PHP pada respons {$hal}
");
        }
        return ['kode' => $kode, 'lokasi' => $lokasi, 'isi' => $isi, 'header' => $header];
    }

    public function csrf(string $halForm): string
    {
        $r = $this->minta($halForm);
        return preg_match('/name="_csrf" value="([a-f0-9]+)"/', $r['isi'], $m) ? $m[1] : '';
    }

    public function masukStaf(string $userid, string $sandi): array
    {
        $t = $this->csrf('masuk-staf');
        return $this->minta('masuk-staf/proses', 'POST', ['_csrf' => $t, 'userid' => $userid, 'kata_sandi' => $sandi]);
    }

    public function masukVendor(string $email, string $sandi): array
    {
        $t = $this->csrf('masuk-vendor');
        return $this->minta('masuk-vendor/proses', 'POST', ['_csrf' => $t, 'email' => $email, 'kata_sandi' => $sandi]);
    }

    /** Kirim POST dengan token CSRF yang diambil dari halaman $halToken. */
    public function kirim(string $hal, array $data, string $halToken, string $tambahanQuery = ''): array
    {
        $data['_csrf'] = $this->csrf($halToken);
        return $this->minta($hal, 'POST', $data, $tambahanQuery);
    }

    public function jar(): string
    {
        return $this->jar;
    }

    public function idSesi(): string
    {
        $isi = (string) file_get_contents($this->jar);
        return preg_match('/vmp_sesi\s+(\S+)/', $isi, $m) ? $m[1] : '';
    }
}
