-- Smart Waste: database bersama untuk Website (User) dan Android (Admin).
CREATE DATABASE IF NOT EXISTS smart_waste CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_waste;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(160) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  nama VARCHAR(120) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'user',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role (role),
  INDEX idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin (
  id_admin BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  nama_admin VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auth_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token_user (user_id),
  INDEX idx_token_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sampah (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode_laporan VARCHAR(60) NOT NULL UNIQUE,
  jenis VARCHAR(40) NOT NULL,
  berat_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
  foto VARCHAR(255) NOT NULL DEFAULT '',
  lokasi VARCHAR(255) NOT NULL,
  deskripsi TEXT NULL,
  tanggal_lapor DATE NULL,
  tingkat_bahaya VARCHAR(20) NOT NULL DEFAULT 'RENDAH',
  status VARCHAR(30) NOT NULL DEFAULT 'menunggu_verifikasi',
  sudah_diambil TINYINT(1) NOT NULL DEFAULT 0,
  id_user BIGINT UNSIGNED NULL,
  petugas_id BIGINT UNSIGNED NULL,
  kendaraan_id BIGINT UNSIGNED NULL,
  tpa_id BIGINT UNSIGNED NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ditugaskan_pada DATETIME NULL,
  diambil_pada DATETIME NULL,
  selesai_pada DATETIME NULL,
  INDEX idx_sampah_user (id_user),
  INDEX idx_sampah_status (status),
  INDEX idx_sampah_type (jenis),
  INDEX idx_sampah_created (dibuat_pada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Arsip menyimpan referensi laporan; baris sampah dan riwayat aslinya tidak dihapus.
CREATE TABLE IF NOT EXISTS laporan_arsip (
  sampah_id BIGINT UNSIGNED PRIMARY KEY,
  diarsipkan_oleh BIGINT UNSIGNED NOT NULL,
  diarsipkan_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_laporan_arsip_created (diarsipkan_pada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Arsip Catatan Admin hanya menyembunyikan catatan dari daftar aktif; laporan tetap utuh.
CREATE TABLE IF NOT EXISTS catatan_admin_arsip (
  sampah_id BIGINT UNSIGNED PRIMARY KEY,
  diarsipkan_oleh BIGINT UNSIGNED NOT NULL,
  diarsipkan_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_catatan_admin_arsip_created (diarsipkan_pada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS riwayat_status (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sampah_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(30) NOT NULL,
  diubah_oleh BIGINT UNSIGNED NULL,
  catatan VARCHAR(255) NOT NULL DEFAULT '',
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_history_waste (sampah_id),
  INDEX idx_history_created (dibuat_pada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel pendukung lama tetap dipertahankan agar data XAMPP sebelumnya tidak hilang.
CREATE TABLE IF NOT EXISTS petugas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL UNIQUE,
  nama VARCHAR(120) NOT NULL,
  zona_tugas VARCHAR(120) NOT NULL DEFAULT '',
  total_ambil INT NOT NULL DEFAULT 0,
  status_aktif TINYINT(1) NOT NULL DEFAULT 1,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kendaraan (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plat_nomor VARCHAR(30) NOT NULL UNIQUE,
  kapasitas_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
  muatan_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
  kondisi VARCHAR(30) NOT NULL DEFAULT 'Baik',
  rute VARCHAR(500) NOT NULL DEFAULT '',
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tpa (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(120) NOT NULL,
  alamat VARCHAR(255) NOT NULL DEFAULT '',
  kapasitas_ton DECIMAL(12,3) NOT NULL DEFAULT 0,
  terisi_ton DECIMAL(12,3) NOT NULL DEFAULT 0,
  tgl_terakhir_dikosongkan VARCHAR(80) NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS laporan_harian (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  total_sampah INT NOT NULL DEFAULT 0,
  total_berat_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
  catatan_json LONGTEXT NULL,
  peringatan_json LONGTEXT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS konfirmasi_penyelesaian (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sampah_id BIGINT UNSIGNED NOT NULL UNIQUE,
  petugas_id BIGINT UNSIGNED NOT NULL,
  foto_bukti VARCHAR(255) NOT NULL,
  catatan_petugas VARCHAR(500) NOT NULL DEFAULT '',
  status VARCHAR(30) NOT NULL DEFAULT 'menunggu',
  direview_oleh BIGINT UNSIGNED NULL,
  dikirim_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  direview_pada DATETIME NULL,
  catatan_admin VARCHAR(500) NOT NULL DEFAULT '',
  INDEX idx_completion_status (status),
  INDEX idx_completion_worker (petugas_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
