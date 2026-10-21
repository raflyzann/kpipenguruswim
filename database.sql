-- ============================================================
-- Skema Database: db_kpi_wahdah
-- WebApp KPI Pengurus Wahdah Islamiyah Makassar
-- ============================================================

CREATE DATABASE IF NOT EXISTS `db_kpi_wahdah` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_kpi_wahdah`;

-- 1. Tabel Pengurus
CREATE TABLE IF NOT EXISTS `pengurus` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(150) NOT NULL,
  `departemen` VARCHAR(150) NOT NULL,
  `status` VARCHAR(100) DEFAULT 'Anggota',
  `no_hp` VARCHAR(30) DEFAULT NULL,
  `sekretaris` TINYINT(1) DEFAULT 0,
  `ketua_departemen` TINYINT(1) DEFAULT 0,
  `akses_departemen` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabel Admin
CREATE TABLE IF NOT EXISTS `admin` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(50) NOT NULL UNIQUE,
  `pin` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Tabel Event
CREATE TABLE IF NOT EXISTS `event` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_event` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Tabel Pengaturan Limit Kegiatan
CREATE TABLE IF NOT EXISTS `pengaturan_limit` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `jenis_kegiatan` VARCHAR(100) NOT NULL UNIQUE,
  `maks_per_minggu` INT DEFAULT 0,
  `maks_per_bulan` INT DEFAULT 0
);

-- 5. Tabel Laporan Kegiatan (KPI)
CREATE TABLE IF NOT EXISTS `laporan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `jenis_kegiatan` VARCHAR(100) NOT NULL,
  `nama` VARCHAR(150) NOT NULL,
  `departemen` VARCHAR(150) NOT NULL,
  `status` VARCHAR(100) DEFAULT NULL,
  `tanggal_kegiatan` DATE NOT NULL,
  `rapat_ke` INT DEFAULT NULL,
  `lokasi` VARCHAR(255) DEFAULT NULL,
  `pemateri_taklim` VARCHAR(150) DEFAULT NULL,
  `nama_event` TEXT DEFAULT NULL,
  `jenis_olahraga` VARCHAR(100) DEFAULT NULL,
  `nama_hasil_gsd` VARCHAR(150) DEFAULT NULL,
  `kategori_pembinaan` VARCHAR(150) DEFAULT NULL,
  `no_hp_hasil_gsd` VARCHAR(30) DEFAULT NULL,
  `saran_catatan` TEXT DEFAULT NULL,
  `skor_kpi` INT DEFAULT 0,
  `link_lampiran` TEXT DEFAULT NULL,
  INDEX idx_nama_dept (`nama`, `departemen`),
  INDEX idx_tgl (`tanggal_kegiatan`),
  INDEX idx_jenis (`jenis_kegiatan`)
);

-- 6. Seed Data Awal Default
INSERT INTO `admin` (`user_id`, `pin`) VALUES ('admin', '654321')
ON DUPLICATE KEY UPDATE `pin` = VALUES(`pin`);

INSERT INTO `pengaturan_limit` (`jenis_kegiatan`, `maks_per_minggu`, `maks_per_bulan`) VALUES
('Rapat Rutin Departemen', 1, 5),
('Taklim Rutin', 1, 5),
('Keaktifan Tarbiyah', 1, 5),
('Olahraga Rutin', 1, 5)
ON DUPLICATE KEY UPDATE `maks_per_minggu` = VALUES(`maks_per_minggu`);

INSERT INTO `event` (`nama_event`) VALUES
('Milad Wahdah Islamiyah'),
('Bakti Sosial Ramadhan'),
('Tabligh Akbar Tahunan');
