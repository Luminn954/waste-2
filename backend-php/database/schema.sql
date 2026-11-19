-- Smart Waste database schema, kept in sync with runtime schema version 3.
-- Contains structure only; report records and user data are not included.
CREATE DATABASE IF NOT EXISTS smart_waste CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_waste;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `admin` (
  `id_admin` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `nama_admin` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL DEFAULT '',
  `password_hash` varchar(255) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diperbarui_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_admin`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `auth_login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `identity_hash` char(64) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_identity_time` (`identity_hash`,`attempted_at`),
  KEY `idx_login_attempts_expiry` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `auth_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_token_user` (`user_id`),
  KEY `idx_token_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `catatan_admin_arsip` (
  `sampah_id` bigint(20) unsigned NOT NULL,
  `diarsipkan_oleh` bigint(20) unsigned NOT NULL,
  `diarsipkan_pada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`sampah_id`),
  KEY `idx_catatan_admin_arsip_created` (`diarsipkan_pada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `google_login_challenges` (
  `nonce_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`nonce_hash`),
  KEY `idx_google_challenge_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `kendaraan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plat_nomor` varchar(30) NOT NULL,
  `kapasitas_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `muatan_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `kondisi` varchar(30) NOT NULL DEFAULT 'Baik',
  `rute` varchar(500) NOT NULL DEFAULT '',
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `plat_nomor` (`plat_nomor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `kendaraan_perbaikan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kendaraan_id` bigint(20) unsigned NOT NULL,
  `petugas_user_id` bigint(20) unsigned NOT NULL,
  `penyebab` varchar(180) NOT NULL,
  `kronologi` text NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'menunggu_persetujuan',
  `progres_terakhir` varchar(800) NOT NULL DEFAULT '',
  `catatan_admin` varchar(800) NOT NULL DEFAULT '',
  `disetujui_oleh` bigint(20) unsigned DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diperbarui_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_repair_vehicle_status` (`kendaraan_id`,`status`),
  KEY `idx_repair_status_date` (`status`,`dibuat_pada`),
  KEY `idx_repair_reporter` (`petugas_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `kendaraan_perbaikan_riwayat` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `perbaikan_id` bigint(20) unsigned NOT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `aksi` varchar(40) NOT NULL,
  `catatan` varchar(800) NOT NULL DEFAULT '',
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_repair_history` (`perbaikan_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `konfirmasi_penyelesaian` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sampah_id` bigint(20) unsigned NOT NULL,
  `petugas_id` bigint(20) unsigned DEFAULT NULL,
  `petugas_nama_snapshot` varchar(120) DEFAULT NULL,
  `foto_bukti` varchar(255) NOT NULL,
  `catatan_petugas` varchar(500) NOT NULL DEFAULT '',
  `status` varchar(30) NOT NULL DEFAULT 'menunggu',
  `direview_oleh` bigint(20) unsigned DEFAULT NULL,
  `dikirim_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `direview_pada` datetime DEFAULT NULL,
  `catatan_admin` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sampah_id` (`sampah_id`),
  KEY `idx_completion_status` (`status`),
  KEY `idx_completion_worker` (`petugas_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `laporan_arsip` (
  `sampah_id` bigint(20) unsigned NOT NULL,
  `diarsipkan_oleh` bigint(20) unsigned NOT NULL,
  `diarsipkan_pada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`sampah_id`),
  KEY `idx_laporan_arsip_created` (`diarsipkan_pada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `laporan_harian` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `total_sampah` int(11) NOT NULL DEFAULT 0,
  `total_berat_kg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `catatan_json` longtext DEFAULT NULL,
  `peringatan_json` longtext DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `permintaan_pengosongan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `jenis_target` varchar(16) NOT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `diminta_oleh` bigint(20) unsigned NOT NULL,
  `alasan` varchar(500) NOT NULL,
  `foto_bukti` varchar(255) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'menunggu',
  `catatan_admin` varchar(500) NOT NULL DEFAULT '',
  `ditinjau_oleh` bigint(20) unsigned DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `ditinjau_pada` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_emptying_target_status` (`jenis_target`,`target_id`,`status`),
  KEY `idx_emptying_status_date` (`status`,`dibuat_pada`),
  KEY `idx_emptying_requester_date` (`diminta_oleh`,`dibuat_pada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `petugas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `nama` varchar(120) NOT NULL,
  `zona_tugas` varchar(120) NOT NULL DEFAULT '',
  `total_ambil` int(11) NOT NULL DEFAULT 0,
  `status_aktif` tinyint(1) NOT NULL DEFAULT 1,
  `diarsipkan_pada` datetime DEFAULT NULL,
  `foto_profil_arsip` varchar(255) DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_petugas_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `petugas_laporan_arsip` (
  `sampah_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `diarsipkan_pada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`sampah_id`,`user_id`),
  KEY `idx_petugas_arsip_user` (`user_id`,`diarsipkan_pada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `riwayat_status` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sampah_id` bigint(20) unsigned NOT NULL,
  `status` varchar(30) NOT NULL,
  `diubah_oleh` bigint(20) unsigned DEFAULT NULL,
  `catatan` varchar(255) NOT NULL DEFAULT '',
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_history_waste` (`sampah_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `sampah` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kode_laporan` varchar(60) NOT NULL,
  `jenis` varchar(40) NOT NULL,
  `berat_kg` decimal(10,2) NOT NULL,
  `foto` varchar(255) NOT NULL DEFAULT '',
  `foto_bukti_lokasi` varchar(255) DEFAULT NULL,
  `foto_lokasi_dikirim_pada` datetime DEFAULT NULL,
  `lokasi` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `tanggal_lapor` date DEFAULT NULL,
  `tingkat_bahaya` varchar(20) NOT NULL DEFAULT 'RENDAH',
  `status` varchar(30) NOT NULL DEFAULT 'menunggu_verifikasi',
  `sudah_diambil` tinyint(1) NOT NULL DEFAULT 0,
  `id_user` bigint(20) unsigned DEFAULT NULL,
  `petugas_id` bigint(20) unsigned DEFAULT NULL,
  `petugas_nama_snapshot` varchar(120) DEFAULT NULL,
  `kendaraan_id` bigint(20) unsigned DEFAULT NULL,
  `tpa_id` bigint(20) unsigned DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `ditugaskan_pada` datetime DEFAULT NULL,
  `diambil_pada` datetime DEFAULT NULL,
  `selesai_pada` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_laporan` (`kode_laporan`),
  KEY `idx_sampah_user` (`id_user`),
  KEY `idx_sampah_status` (`status`),
  KEY `idx_sampah_created` (`dibuat_pada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `smartwaste_schema_version` (
  `version` int(10) unsigned NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `tpa` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(120) NOT NULL,
  `alamat` varchar(255) NOT NULL DEFAULT '',
  `kapasitas_ton` decimal(12,3) NOT NULL DEFAULT 0.000,
  `terisi_ton` decimal(12,3) NOT NULL DEFAULT 0.000,
  `tgl_terakhir_dikosongkan` varchar(80) DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(60) NOT NULL,
  `email` varchar(160) NOT NULL DEFAULT '',
  `password_hash` varchar(255) NOT NULL,
  `nama` varchar(120) NOT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diperbarui_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_super_admin` tinyint(1) NOT NULL DEFAULT 0,
  `google_sub` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `uq_users_google_sub` (`google_sub`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
