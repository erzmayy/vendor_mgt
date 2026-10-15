-- =====================================================================
-- DATA AWAL PROTOTYPE (FIKTIF, HANYA UNTUK LINGKUNGAN LOKAL)
-- Jalankan setelah schema.sql.
--
-- Password SEMUA akun demo: DemoLokal#2026
-- Jangan dipakai di produksi dan jangan dipakai di sistem asli.
-- Tidak ada data pegawai atau vendor asli di file ini.
-- =====================================================================

USE `vendor_management_prototype`;

-- Akun staf demo (bentuk mengikuti tbl_user SIAP; password di password_new)
INSERT INTO `tbl_user`
  (`id`,`userid`,`password`,`password_new`,`nama`,`dept`,`divisi`,`email`,`status_wa`,`pusat`,`ganti_password`)
VALUES
  (901,'demo_plan', '', '$2y$10$IMPI/UFIAQgOsC1hn6bY2O4wkLu2HFqCWd9KRdehz6PxgAEH46QHm','Demo Perencanaan','Divisi Perencanaan','Analis','demo.plan@contoh.test','BLM','YES','1'),
  (902,'demo_ver1', '', '$2y$10$IMPI/UFIAQgOsC1hn6bY2O4wkLu2HFqCWd9KRdehz6PxgAEH46QHm','Demo Verifikator 1','Divisi Perencanaan','Pengelola','demo.ver1@contoh.test','BLM','YES','1'),
  (903,'demo_ver2', '', '$2y$10$IMPI/UFIAQgOsC1hn6bY2O4wkLu2HFqCWd9KRdehz6PxgAEH46QHm','Demo Verifikator 2','Divisi Pemasaran','Analis','demo.ver2@contoh.test','BLM','YES','1'),
  (904,'demo_auth', '', '$2y$10$IMPI/UFIAQgOsC1hn6bY2O4wkLu2HFqCWd9KRdehz6PxgAEH46QHm','Demo Petugas Berwenang','Pengurus','Pemimpin Divisi','demo.auth@contoh.test','BLM','YES','1'),
  (905,'demo_admin','', '$2y$10$IMPI/UFIAQgOsC1hn6bY2O4wkLu2HFqCWd9KRdehz6PxgAEH46QHm','Demo Administrator','ALL','','demo.admin@contoh.test','BLM','NO','1'),
  (906,'demo_tanpa_peran','','$2y$10$IMPI/UFIAQgOsC1hn6bY2O4wkLu2HFqCWd9KRdehz6PxgAEH46QHm','Demo Tanpa Peran (uji akses ditolak)','Divisi Pelatihan','Pengelola','demo.tanpa@contoh.test','BLM','YES','1');

-- Pemetaan manual peran Vendor Management
INSERT INTO `tbl_peran_user` (`id_user`,`userid`,`peran`,`aktif`,`ditetapkan_oleh`) VALUES
  (905,'demo_admin','admin',      1,905),
  (901,'demo_plan', 'perencanaan',1,905),
  (902,'demo_ver1', 'verifikator',1,905),
  (903,'demo_ver2', 'verifikator',1,905),
  (904,'demo_auth', 'berwenang',  1,905);

-- Akun vendor demo
INSERT INTO `tbl_akun_vendor` (`email`,`kata_sandi`,`nama_kontak`) VALUES
  ('vendor.demo@contoh.test','$2y$10$IMPI/UFIAQgOsC1hn6bY2O4wkLu2HFqCWd9KRdehz6PxgAEH46QHm','Demo Vendor');

-- Persyaratan dokumen: CONTOH SEMENTARA, bukan ketentuan resmi PPU
INSERT INTO `tbl_syarat_dokumen` (`kode_syarat`,`nama_dokumen`,`keterangan`,`wajib`,`urutan`) VALUES
  ('akta_pendirian','Akta Pendirian Perusahaan','Contoh sementara',1,1),
  ('npwp','NPWP Perusahaan','Contoh sementara',1,2),
  ('nib','Nomor Induk Berusaha (NIB)','Contoh sementara',1,3),
  ('domisili','Surat Keterangan Domisili','Contoh sementara',0,4),
  ('profil','Profil Perusahaan','Contoh sementara',0,5);

-- Field tambahan formulir: CONTOH SEMENTARA
INSERT INTO `tbl_field_formulir` (`kode_field`,`label`,`tipe_input`,`opsi_pilihan`,`wajib`,`urutan`) VALUES
  ('jumlah_karyawan','Jumlah karyawan','angka',NULL,0,1),
  ('tahun_berdiri','Tahun berdiri','angka',NULL,0,2),
  ('situs_web','Situs web','teks',NULL,0,3),
  ('pengalaman_singkat','Pengalaman singkat','area_teks',NULL,0,4);

-- Aturan klasifikasi: CONTOH NETRAL untuk pengujian, bukan aturan resmi PPU.
-- Skor total = rata-rata tertimbang kelengkapan berkas, pertanyaan penilaian, dan hasil verifikasi
-- (bobot di tbl_pengaturan_penilaian).
INSERT INTO `tbl_aturan_klasifikasi` (`label_klasifikasi`,`skor_min`,`skor_maks`,`urutan`) VALUES
  ('Level A (simulasi)',80.00,100.00,1),
  ('Level B (simulasi)',60.00, 79.99,2),
  ('Level C (simulasi)', 0.00, 59.99,3);

-- Penilaian otomatis: bobot, pertanyaan, dan opsi jawaban. CONTOH SIMULASI, bukan ketentuan resmi PPU.
INSERT INTO `tbl_pengaturan_penilaian` (`kunci`,`nilai`,`keterangan`) VALUES
  ('bobot_berkas','40','Bobot komponen kelengkapan berkas (contoh)'),
  ('bobot_pertanyaan','40','Bobot komponen pertanyaan penilaian (contoh)'),
  ('bobot_verifikasi','20','Bobot komponen hasil verifikasi petugas (contoh)');

INSERT INTO `tbl_pertanyaan_penilaian` (`kode_pertanyaan`,`teks_pertanyaan`,`bobot`,`wajib`,`urutan`) VALUES
  ('lama_usaha','Berapa lama perusahaan Anda telah beroperasi?',3,1,1),
  ('pengalaman','Apakah perusahaan memiliki pengalaman menangani pekerjaan sejenis?',3,1,2),
  ('tenaga_kerja','Berapa jumlah tenaga kerja tetap perusahaan?',2,1,3),
  ('mutu','Apakah perusahaan memiliki pengendalian mutu atau sertifikasi?',2,1,4);

INSERT INTO `tbl_opsi_jawaban` (`id_pertanyaan`,`teks_opsi`,`poin`,`urutan`)
SELECT p.id_pertanyaan, o.teks, o.poin, o.urutan FROM tbl_pertanyaan_penilaian p
JOIN (
  SELECT 'lama_usaha' k, 'Kurang dari 1 tahun' teks, 20 poin, 1 urutan UNION ALL
  SELECT 'lama_usaha', '1 sampai 3 tahun', 50, 2 UNION ALL
  SELECT 'lama_usaha', 'Lebih dari 3 sampai 5 tahun', 75, 3 UNION ALL
  SELECT 'lama_usaha', 'Lebih dari 5 tahun', 100, 4 UNION ALL
  SELECT 'pengalaman', 'Belum pernah', 20, 1 UNION ALL
  SELECT 'pengalaman', '1 sampai 2 pekerjaan', 60, 2 UNION ALL
  SELECT 'pengalaman', 'Lebih dari 2 pekerjaan', 100, 3 UNION ALL
  SELECT 'tenaga_kerja', '1 sampai 5 orang', 30, 1 UNION ALL
  SELECT 'tenaga_kerja', '6 sampai 20 orang', 60, 2 UNION ALL
  SELECT 'tenaga_kerja', 'Lebih dari 20 orang', 100, 3 UNION ALL
  SELECT 'mutu', 'Tidak ada', 20, 1 UNION ALL
  SELECT 'mutu', 'Memiliki prosedur internal', 60, 2 UNION ALL
  SELECT 'mutu', 'Memiliki sertifikasi', 100, 3
) o ON o.k = p.kode_pertanyaan
WHERE NOT EXISTS (SELECT 1 FROM tbl_opsi_jawaban x WHERE x.id_pertanyaan = p.id_pertanyaan);
