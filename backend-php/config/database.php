<?php
declare(strict_types=1);

require_once __DIR__ . '/environment.php';

date_default_timezone_set('Asia/Jakarta');

// Default tetap cocok untuk XAMPP lokal; deployment dapat mengatur environment.
function smartWasteEnv(string $name, string $fallback): string
{
    $value = getenv($name);
    return $value === false ? $fallback : trim($value);
}

define('DB_HOST', smartWasteEnv('SMARTWASTE_DB_HOST', '127.0.0.1'));
define('DB_PORT', smartWasteEnv('SMARTWASTE_DB_PORT', '3306'));
define('DB_NAME', smartWasteEnv('SMARTWASTE_DB_NAME', 'smart_waste'));
define('DB_USER', smartWasteEnv('SMARTWASTE_DB_USER', 'root'));
define('DB_PASS', smartWasteEnv('SMARTWASTE_DB_PASSWORD', ''));

if (!preg_match('/\\A[A-Za-z0-9_]+\\z/', DB_NAME)) {
    throw new RuntimeException('SMARTWASTE_DB_NAME hanya boleh berisi huruf, angka, dan underscore.');
}
if (!ctype_digit(DB_PORT) || (int) DB_PORT < 1 || (int) DB_PORT > 65535) {
    throw new RuntimeException('SMARTWASTE_DB_PORT harus berupa port TCP yang valid.');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            $options
        );
    } catch (PDOException $firstError) {
        $server = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            $options
        );
        $server->exec(
            'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            $options
        );
    }

    initialiseSchema($pdo);
    seedDefaults($pdo);
    return $pdo;
}

function initialiseSchema(PDO $pdo): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS users (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS admin (
            id_admin BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL UNIQUE,
            nama_admin VARCHAR(120) NOT NULL,
            email VARCHAR(160) NOT NULL DEFAULT '',
            password_hash VARCHAR(255) NOT NULL,
            aktif TINYINT(1) NOT NULL DEFAULT 1,
            dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_admin_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS auth_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token_user (user_id),
            INDEX idx_token_expiry (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS sampah (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS laporan_arsip (
            sampah_id BIGINT UNSIGNED PRIMARY KEY,
            diarsipkan_oleh BIGINT UNSIGNED NOT NULL,
            diarsipkan_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_laporan_arsip_created (diarsipkan_pada)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS catatan_admin_arsip (
            sampah_id BIGINT UNSIGNED PRIMARY KEY,
            diarsipkan_oleh BIGINT UNSIGNED NOT NULL,
            diarsipkan_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_catatan_admin_arsip_created (diarsipkan_pada)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS riwayat_status (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sampah_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(30) NOT NULL,
            diubah_oleh BIGINT UNSIGNED NULL,
            catatan VARCHAR(255) NOT NULL DEFAULT '',
            dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_history_waste (sampah_id),
            INDEX idx_history_created (dibuat_pada)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS petugas (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL UNIQUE,
            nama VARCHAR(120) NOT NULL,
            zona_tugas VARCHAR(120) NOT NULL DEFAULT '',
            total_ambil INT NOT NULL DEFAULT 0,
            status_aktif TINYINT(1) NOT NULL DEFAULT 1,
            dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS kendaraan (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plat_nomor VARCHAR(30) NOT NULL UNIQUE,
            kapasitas_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
            muatan_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
            kondisi VARCHAR(30) NOT NULL DEFAULT 'Baik',
            rute VARCHAR(500) NOT NULL DEFAULT '',
            dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS tpa (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(120) NOT NULL,
            alamat VARCHAR(255) NOT NULL DEFAULT '',
            kapasitas_ton DECIMAL(12,3) NOT NULL DEFAULT 0,
            terisi_ton DECIMAL(12,3) NOT NULL DEFAULT 0,
            tgl_terakhir_dikosongkan VARCHAR(80) NULL,
            dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS laporan_harian (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            total_sampah INT NOT NULL DEFAULT 0,
            total_berat_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
            catatan_json LONGTEXT NULL,
            peringatan_json LONGTEXT NULL,
            dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS konfirmasi_penyelesaian (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    // Migrasi aman untuk database Waste 2 yang sudah pernah dipakai.
    addColumnIfMissing($pdo, 'users', 'email', "VARCHAR(160) NOT NULL DEFAULT '' AFTER username");
    addColumnIfMissing($pdo, 'users', 'is_super_admin', 'TINYINT(1) NOT NULL DEFAULT 0');
    addColumnIfMissing($pdo, 'petugas', 'user_id', 'BIGINT UNSIGNED NULL AFTER id');
    addColumnIfMissing($pdo, 'sampah', 'foto', "VARCHAR(255) NOT NULL DEFAULT '' AFTER berat_kg");
    addColumnIfMissing($pdo, 'sampah', 'deskripsi', "TEXT NULL AFTER lokasi");
    addColumnIfMissing($pdo, 'sampah', 'tanggal_lapor', "DATE NULL AFTER deskripsi");
    addColumnIfMissing($pdo, 'sampah', 'tingkat_bahaya', "VARCHAR(20) NOT NULL DEFAULT 'RENDAH'");
    addColumnIfMissing($pdo, 'sampah', 'petugas_id', 'BIGINT UNSIGNED NULL');
    addColumnIfMissing($pdo, 'sampah', 'kendaraan_id', 'BIGINT UNSIGNED NULL');
    addColumnIfMissing($pdo, 'sampah', 'tpa_id', 'BIGINT UNSIGNED NULL');
    addColumnIfMissing($pdo, 'sampah', 'ditugaskan_pada', 'DATETIME NULL');
    addColumnIfMissing($pdo, 'sampah', 'diambil_pada', 'DATETIME NULL');
    addColumnIfMissing($pdo, 'sampah', 'selesai_pada', 'DATETIME NULL');
    addColumnIfMissing($pdo, 'kendaraan', 'kapasitas_kg', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    addColumnIfMissing($pdo, 'kendaraan', 'muatan_kg', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    addColumnIfMissing($pdo, 'kendaraan', 'kondisi', "VARCHAR(30) NOT NULL DEFAULT 'Baik'");
    addColumnIfMissing($pdo, 'kendaraan', 'rute', "VARCHAR(500) NOT NULL DEFAULT ''");
    addColumnIfMissing($pdo, 'tpa', 'alamat', "VARCHAR(255) NOT NULL DEFAULT ''");
    addColumnIfMissing($pdo, 'tpa', 'kapasitas_ton', 'DECIMAL(12,3) NOT NULL DEFAULT 0');
    addColumnIfMissing($pdo, 'tpa', 'terisi_ton', 'DECIMAL(12,3) NOT NULL DEFAULT 0');
    addColumnIfMissing($pdo, 'petugas', 'zona_tugas', "VARCHAR(120) NOT NULL DEFAULT ''");
    addColumnIfMissing($pdo, 'petugas', 'total_ambil', 'INT NOT NULL DEFAULT 0');
    addColumnIfMissing($pdo, 'petugas', 'status_aktif', 'TINYINT(1) NOT NULL DEFAULT 1');
    ensureUniqueIndex($pdo, 'petugas', 'uq_petugas_user_id', 'user_id');
    widenColumnIfNeeded($pdo, 'sampah', 'status', "VARCHAR(30) NOT NULL DEFAULT 'menunggu_verifikasi'");
    widenColumnIfNeeded($pdo, 'riwayat_status', 'status', "VARCHAR(30) NOT NULL");

    // Jangan meratakan status operasional baru ke "diproses". Migrasi lama ini
    // dijalankan saat API diakses, jadi pemetaan harus tetap idempoten.
    $pdo->exec("UPDATE sampah SET status = 'menunggu_verifikasi' WHERE status = 'menunggu'");
    $pdo->exec("UPDATE sampah SET tanggal_lapor = DATE(dibuat_pada) WHERE tanggal_lapor IS NULL");
}

function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!columnExists($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }
}

function ensureUniqueIndex(PDO $pdo, string $table, string $index, string $column): void
{
    if (!in_array($table, ['petugas', 'users'], true)) {
        return;
    }
    $stmt = $pdo->prepare('SHOW INDEX FROM ' . $table . ' WHERE Column_name = ? AND Non_unique = 0');
    $stmt->execute([$column]);
    if (!$stmt->fetch()) {
        $pdo->exec('ALTER TABLE ' . $table . ' ADD UNIQUE KEY ' . $index . ' (' . $column . ')');
    }
}

function widenColumnIfNeeded(PDO $pdo, string $table, string $column, string $definition): void
{
    // MODIFY juga aman dijalankan berulang kali dan memastikan status baru tidak terpotong.
    if (columnExists($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE ' . $table . ' MODIFY COLUMN ' . $column . ' ' . $definition);
    }
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $allowedTables = ['users', 'sampah', 'riwayat_status', 'petugas', 'kendaraan', 'tpa'];
    if (!in_array($table, $allowedTables, true)) {
        return false;
    }
    $columns = $pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll();
    foreach ($columns as $item) {
        if (($item['Field'] ?? '') === $column) {
            return true;
        }
    }
    return false;
}

function seedDefaults(PDO $pdo): void
{
    $userStmt = $pdo->prepare('SELECT id, password_hash, role, aktif, email, nama FROM users WHERE username = ? LIMIT 1');
    $insertUser = $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, nama, role, aktif) VALUES (?, ?, ?, ?, ?, 1)'
    );

    $defaults = [
        ['admin', 'admin@smartwaste.local', 'Admin Smart Waste', 'admin'],
        ['user', 'user@smartwaste.local', 'User Smart Waste', 'user'],
    ];

    foreach ($defaults as [$username, $email, $name, $role]) {
        $userStmt->execute([$username]);
        $existing = $userStmt->fetch();
        if (!$existing) {
            $insertUser->execute([$username, $email, password_hash('12345', PASSWORD_DEFAULT), $name, $role]);
            continue;
        }

        // Akun demo tetap dapat dipakai setelah migrasi versi lama, tanpa menimpa password custom.
        if (empty($existing['email'])) {
            $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, $existing['id']]);
        }
        if ((string) $existing['role'] !== $role) {
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $existing['id']]);
        }
    }

    // Akun demo "admin" adalah bootstrap admin pertama. Email hanya data profil;
    // login selalu memakai username dan kata sandi.
    $bootstrapEmail = strtolower(trim((string) getenv('SMARTWASTE_FIRST_ADMIN_EMAIL')));
    if ($bootstrapEmail !== '' && filter_var($bootstrapEmail, FILTER_VALIDATE_EMAIL)) {
        $pdo->prepare(
            "UPDATE users SET email = ? WHERE username = 'admin' AND role = 'admin'
             AND (email = '' OR email = 'admin@smartwaste.local')"
        )->execute([$bootstrapEmail]);
    }
    $pdo->exec("UPDATE users SET is_super_admin = 1 WHERE username = 'admin' AND role = 'admin'");

    $admin = $pdo->query("SELECT id, nama, email, password_hash, aktif FROM users WHERE username = 'admin' LIMIT 1")->fetch();
    if ($admin) {
        $adminCheck = $pdo->prepare('SELECT id_admin FROM admin WHERE user_id = ? LIMIT 1');
        $adminCheck->execute([$admin['id']]);
        if (!$adminCheck->fetch()) {
            $pdo->prepare(
                'INSERT INTO admin (user_id, nama_admin, email, password_hash, aktif) VALUES (?, ?, ?, ?, ?)'
            )->execute([$admin['id'], $admin['nama'], $admin['email'], $admin['password_hash'], $admin['aktif']]);
        } else {
            $pdo->prepare(
                'UPDATE admin SET nama_admin = ?, email = ?, password_hash = ?, aktif = ? WHERE user_id = ?'
            )->execute([$admin['nama'], $admin['email'], $admin['password_hash'], $admin['aktif'], $admin['id']]);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM sampah')->fetchColumn() === 0) {
        $userId = (int) $pdo->query("SELECT id FROM users WHERE username = 'user' LIMIT 1")->fetchColumn();
        $stmt = $pdo->prepare(
            'INSERT INTO sampah (kode_laporan, jenis, berat_kg, lokasi, deskripsi, tanggal_lapor, status, id_user)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(['SW-001', 'Plastik', 1.2, 'Jl. Melati', 'Sampah plastik di sisi jalan.', date('Y-m-d'), 'selesai', $userId]);
        $stmt->execute(['SW-002', 'Organik', 2.5, 'Pasar Lama', 'Tumpukan sisa sayur dari pasar.', date('Y-m-d'), 'diproses', $userId]);
        $stmt->execute(['SW-003', 'Kertas', 0.8, 'Kampus Utama', 'Kardus dan kertas bekas.', date('Y-m-d'), 'menunggu_verifikasi', $userId]);
    }

    // Setiap laporan selalu punya jejak status awal/terakhir.
    $reports = $pdo->query('SELECT id, status FROM sampah')->fetchAll();
    $historyCheck = $pdo->prepare('SELECT id FROM riwayat_status WHERE sampah_id = ? ORDER BY id ASC LIMIT 1');
    $historyInsert = $pdo->prepare(
        'INSERT INTO riwayat_status (sampah_id, status, diubah_oleh, catatan) VALUES (?, ?, NULL, ?)'
    );
    foreach ($reports as $report) {
        $historyCheck->execute([$report['id']]);
        if (!$historyCheck->fetch()) {
            $historyInsert->execute([$report['id'], $report['status'], 'Status awal dari data laporan']);
        }
    }
}
