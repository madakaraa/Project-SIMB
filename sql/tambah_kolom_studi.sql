-- OPSIONAL: kolom ini sudah dibuat otomatis oleh api/admin.php saat halaman admin dibuka.
-- Jalankan manual hanya jika ingin (database: pyrowatch). Aman diulang (MariaDB/XAMPP).
ALTER TABLE `studi_kasus`
  ADD COLUMN IF NOT EXISTS `gambar`        VARCHAR(255) NULL AFTER `deskripsi`,
  ADD COLUMN IF NOT EXISTS `link_berita`   VARCHAR(500) NULL AFTER `gambar`,
  ADD COLUMN IF NOT EXISTS `tipe_dominan`  VARCHAR(120) NULL AFTER `link_berita`,
  ADD COLUMN IF NOT EXISTS `faktor_pemicu` VARCHAR(160) NULL AFTER `tipe_dominan`;
