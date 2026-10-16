<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Max-Age: 86400');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $message, int $status = 400, array $extra = []): never
{
    respond(array_merge(['message' => $message], $extra), $status);
}

function body(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function requestPath(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    if ($scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
        $uri = substr($uri, strlen($scriptDir));
    }
    $uri = trim((string) $uri, '/');
    if (str_starts_with($uri, 'index.php')) {
        $uri = trim(substr($uri, strlen('index.php')), '/');
    }
    return $uri;
}

function segments(): array
{
    $path = requestPath();
    return $path === '' ? [] : explode('/', $path);
}

function bearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    if (preg_match('/Bearer\\s+(.+)/i', (string) $header, $match)) {
        return trim($match[1]);
    }
    return null;
}

function authUser(PDO $pdo): ?array
{
    $token = bearerToken();
    if (!$token) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT u.id, u.username, u.email, u.nama, u.role, u.aktif, u.is_super_admin
         FROM auth_tokens t JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ? AND t.expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $user = $stmt->fetch();
    if (!$user || !(int) $user['aktif']) {
        return null;
    }
    return $user;
}

function requireUser(PDO $pdo, ?string $role = null): array
{
    $user = authUser($pdo);
    if (!$user) {
        fail('Sesi tidak valid atau sudah berakhir. Silakan login kembali.', 401);
    }
    if ($role !== null && $user['role'] !== $role) {
        $label = match ($role) {
            'admin' => 'admin',
            'petugas' => 'petugas',
            default => 'user',
        };
        fail('Akses khusus ' . $label . '.', 403);
    }
    return $user;
}

function publicUser(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'id_user' => (int) $user['id'],
        'username' => (string) $user['username'],
        'email' => (string) ($user['email'] ?? ''),
        'name' => (string) $user['nama'],
        'nama' => (string) $user['nama'],
        'role' => (string) $user['role'],
        'active' => (bool) $user['aktif'],
        'is_super_admin' => (bool) ($user['is_super_admin'] ?? false),
    ];
}

function statusLabel(string $status): string
{
    return [
        'menunggu_verifikasi' => 'Menunggu Verifikasi',
        'diproses' => 'Diproses',
        'ditugaskan' => 'Ditugaskan',
        'diangkut' => 'Diangkut',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
    ][$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function normalizeStatus(string $status): ?string
{
    $key = strtolower(trim($status));
    $aliases = [
        'menunggu' => 'menunggu_verifikasi',
        'menunggu verifikasi' => 'menunggu_verifikasi',
        'menunggu_verifikasi' => 'menunggu_verifikasi',
        'pending' => 'menunggu_verifikasi',
        'diproses' => 'diproses',
        'ditugaskan' => 'ditugaskan',
        'diangkut' => 'diangkut',
        'selesai' => 'selesai',
        'ditolak' => 'ditolak',
        'rejected' => 'ditolak',
    ];
    return $aliases[$key] ?? null;
}

function apiBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    return $scheme . '://' . $host . rtrim($directory, '/');
}

function photoUrl(?string $path): ?string
{
    $path = trim((string) $path);
    if ($path === '') {
        return null;
    }
    if (preg_match('/^https?:\\/\\//i', $path)) {
        return $path;
    }
    return apiBaseUrl() . '/' . ltrim($path, '/');
}

function statusHistory(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT r.id, r.status, r.catatan, r.dibuat_pada, r.diubah_oleh,
                COALESCE(u.nama, "Sistem") AS changed_by
         FROM riwayat_status r LEFT JOIN users u ON u.id = r.diubah_oleh
         WHERE r.sampah_id = ? ORDER BY r.id ASC'
    );
    $stmt->execute([$id]);
    return array_map(static function (array $item): array {
        return [
            'id' => (int) $item['id'],
            'status' => $item['status'],
            'status_label' => statusLabel((string) $item['status']),
            'note' => (string) ($item['catatan'] ?? ''),
            'catatan' => (string) ($item['catatan'] ?? ''),
            'changed_by' => (string) $item['changed_by'],
            'tanggal_update' => $item['dibuat_pada'],
        ];
    }, $stmt->fetchAll());
}

function reportRow(PDO $pdo, array $row, bool $includeHistory = false): array
{
    $status = (string) ($row['status'] ?? 'menunggu_verifikasi');
    $result = [
        'id' => (int) $row['id'],
        'id_laporan' => (int) $row['id'],
        'code' => (string) $row['kode_laporan'],
        'kode_laporan' => (string) $row['kode_laporan'],
        'type' => (string) $row['jenis'],
        'jenis' => (string) $row['jenis'],
        'jenis_sampah' => (string) $row['jenis'],
        'weight_kg' => (float) $row['berat_kg'],
        'berat_kg' => (float) $row['berat_kg'],
        'photo' => photoUrl($row['foto'] ?? null),
        'foto' => photoUrl($row['foto'] ?? null),
        'location' => (string) $row['lokasi'],
        'lokasi' => (string) $row['lokasi'],
        'description' => (string) ($row['deskripsi'] ?? ''),
        'deskripsi' => (string) ($row['deskripsi'] ?? ''),
        'report_date' => (string) ($row['tanggal_lapor'] ?: substr((string) $row['dibuat_pada'], 0, 10)),
        'tanggal_lapor' => (string) ($row['tanggal_lapor'] ?: substr((string) $row['dibuat_pada'], 0, 10)),
        'created_at' => $row['dibuat_pada'],
        'status' => $status,
        'status_label' => statusLabel($status),
        'hazard_level' => (string) ($row['tingkat_bahaya'] ?? 'RENDAH'),
        'tingkat_bahaya' => (string) ($row['tingkat_bahaya'] ?? 'RENDAH'),
        'user_id' => $row['id_user'] === null ? null : (int) $row['id_user'],
        'id_user' => $row['id_user'] === null ? null : (int) $row['id_user'],
        'reported_by' => (string) ($row['nama_user'] ?? 'User'),
        'reported_username' => (string) ($row['username_user'] ?? ''),
        'worker_id' => $row['petugas_id'] === null ? null : (int) $row['petugas_id'],
        'worker_name' => (string) ($row['nama_petugas'] ?? ''),
        'vehicle_id' => $row['kendaraan_id'] === null ? null : (int) $row['kendaraan_id'],
        'vehicle_plate' => (string) ($row['plat_nomor'] ?? ''),
        'tpa_id' => $row['tpa_id'] === null ? null : (int) $row['tpa_id'],
        'tpa_name' => (string) ($row['nama_tpa'] ?? ''),
    ];
    if ($includeHistory) {
        $result['timeline'] = statusHistory($pdo, (int) $row['id']);
        $result['riwayat'] = $result['timeline'];
    }
    return $result;
}

function reportQuery(PDO $pdo, ?int $userId = null, ?string $status = null, ?string $search = null, ?int $workerId = null): array
{
    $where = [];
    $params = [];
    if ($userId !== null) {
        $where[] = 's.id_user = ?';
        $params[] = $userId;
    }
    if ($workerId !== null) {
        $where[] = 's.petugas_id = ?';
        $params[] = $workerId;
    }
    if ($status !== null) {
        if ($status === 'diproses') {
            $where[] = "s.status IN ('diproses', 'ditugaskan', 'diangkut')";
        } else {
            $where[] = 's.status = ?';
            $params[] = $status;
        }
    }
    if ($search !== null && trim($search) !== '') {
        $where[] = '(s.kode_laporan LIKE ? OR s.jenis LIKE ? OR s.lokasi LIKE ? OR u.nama LIKE ?)';
        $term = '%' . trim($search) . '%';
        array_push($params, $term, $term, $term, $term);
    }
    $sql =
        'SELECT s.*, u.nama AS nama_user, u.username AS username_user,
                p.nama AS nama_petugas, k.plat_nomor, t.nama AS nama_tpa
         FROM sampah s
         LEFT JOIN users u ON u.id = s.id_user
         LEFT JOIN petugas p ON p.id = s.petugas_id
         LEFT JOIN kendaraan k ON k.id = s.kendaraan_id
         LEFT JOIN tpa t ON t.id = s.tpa_id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY s.dibuat_pada DESC, s.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function findReport(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT s.*, u.nama AS nama_user, u.username AS username_user,
                p.nama AS nama_petugas, k.plat_nomor, t.nama AS nama_tpa
         FROM sampah s
         LEFT JOIN users u ON u.id = s.id_user
         LEFT JOIN petugas p ON p.id = s.petugas_id
         LEFT JOIN kendaraan k ON k.id = s.kendaraan_id
         LEFT JOIN tpa t ON t.id = s.tpa_id
         WHERE s.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function saveUploadedPhoto(): string
{
    if (!isset($_FILES['foto']) || !is_array($_FILES['foto'])) {
        return '';
    }
    $file = $_FILES['foto'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        fail('Foto gagal diunggah.', 422);
    }
    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        fail('Ukuran foto maksimal 5 MB.', 422);
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    $mime = function_exists('finfo_open') ? (static function () use ($tmp): string {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $value = $finfo ? (string) finfo_file($finfo, $tmp) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        return $value;
    })() : (string) ($file['type'] ?? '');
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($extensions[$mime])) {
        fail('Format foto harus JPG, PNG, WEBP, atau GIF.', 422);
    }
    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        fail('Folder upload tidak dapat dibuat.', 500);
    }
    $filename = 'laporan_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $target = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp, $target)) {
        fail('Foto tidak dapat disimpan.', 500);
    }
    return 'uploads/' . $filename;
}

function newReportCode(PDO $pdo): string
{
    do {
        $code = 'SW-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $stmt = $pdo->prepare('SELECT id FROM sampah WHERE kode_laporan = ? LIMIT 1');
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

function dashboard(PDO $pdo): array
{
    $totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $totalReports = (int) $pdo->query('SELECT COUNT(*) FROM sampah')->fetchColumn();
    $counts = [
        'menunggu_verifikasi' => 0,
        'diproses' => 0,
        'ditugaskan' => 0,
        'diangkut' => 0,
        'selesai' => 0,
        'ditolak' => 0,
    ];
    $rows = $pdo->query('SELECT status, COUNT(*) AS total FROM sampah GROUP BY status')->fetchAll();
    foreach ($rows as $row) {
        $key = normalizeStatus((string) $row['status']);
        if ($key !== null) {
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row['total'];
        }
    }
    $totalWeight = (float) $pdo->query('SELECT COALESCE(SUM(berat_kg), 0) FROM sampah')->fetchColumn();
    $activeWorkers = (int) $pdo->query(
        'SELECT COUNT(*) FROM petugas p
         INNER JOIN users u ON u.id = p.user_id
         WHERE p.status_aktif = 1 AND u.aktif = 1'
    )->fetchColumn();
    $vehicleStats = $pdo->query(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN LOWER(k.kondisi) = 'baik'
                    AND k.muatan_kg + COALESCE((
                        SELECT SUM(s.berat_kg) FROM sampah s
                        WHERE s.kendaraan_id = k.id AND s.status = 'ditugaskan'
                    ), 0) < k.kapasitas_kg THEN 1 ELSE 0 END) AS available
         FROM kendaraan k"
    )->fetch();
    $tpaStats = $pdo->query(
        'SELECT COALESCE(SUM(kapasitas_ton), 0) AS capacity, COALESCE(SUM(terisi_ton), 0) AS used FROM tpa'
    )->fetch();
    $hazardAlerts = (int) $pdo->query(
        "SELECT COUNT(*) FROM sampah
         WHERE UPPER(tingkat_bahaya) IN ('TINGGI', 'B3', 'BERBAHAYA', 'HIGH', 'CRITICAL')
           AND status NOT IN ('selesai', 'ditolak')"
    )->fetchColumn();
    $totalCapacity = (float) ($tpaStats['capacity'] ?? 0);
    $usedCapacity = (float) ($tpaStats['used'] ?? 0);
    $reservedCapacity = (float) $pdo->query(
        "SELECT COALESCE(SUM(berat_kg), 0) / 1000 FROM sampah
         WHERE tpa_id IS NOT NULL AND status IN ('ditugaskan', 'diangkut')"
    )->fetchColumn();
    $processing = $counts['diproses'] + $counts['ditugaskan'] + $counts['diangkut'];
    return [
        'total_users' => $totalUsers,
        'total_user' => $totalUsers,
        'total_reports' => $totalReports,
        'total_laporan' => $totalReports,
        'waiting' => $counts['menunggu_verifikasi'],
        'menunggu_verifikasi' => $counts['menunggu_verifikasi'],
        'processing' => $processing,
        'diproses' => $processing,
        'assigned' => $counts['ditugaskan'],
        'ditugaskan' => $counts['ditugaskan'],
        'transported' => $counts['diangkut'],
        'diangkut' => $counts['diangkut'],
        'completed' => $counts['selesai'],
        'selesai' => $counts['selesai'],
        'rejected' => $counts['ditolak'],
        'ditolak' => $counts['ditolak'],
        'total_weight_kg' => $totalWeight,
        'active_workers' => $activeWorkers,
        'vehicle_count' => (int) ($vehicleStats['total'] ?? 0),
        'available_vehicles' => (int) ($vehicleStats['available'] ?? 0),
        'tpa_capacity_ton' => $totalCapacity,
        'tpa_used_ton' => $usedCapacity,
        'tpa_reserved_ton' => $reservedCapacity,
        'tpa_utilization_percent' => $totalCapacity > 0 ? min(100, round(($usedCapacity + $reservedCapacity) / $totalCapacity * 100, 1)) : 0,
        'hazard_alerts' => $hazardAlerts,
        'status_counts' => $counts,
    ];
}

function statistics(PDO $pdo): array
{
    $monthly = $pdo->query(
        "SELECT DATE_FORMAT(COALESCE(tanggal_lapor, dibuat_pada), '%Y-%m') AS periode,
                COUNT(*) AS total
         FROM sampah GROUP BY periode ORDER BY periode DESC LIMIT 12"
    )->fetchAll();
    $types = $pdo->query(
        'SELECT jenis AS jenis_sampah, COUNT(*) AS total, COALESCE(SUM(berat_kg), 0) AS berat_kg
         FROM sampah GROUP BY jenis ORDER BY total DESC'
    )->fetchAll();
    $completed = (int) $pdo->query("SELECT COUNT(*) FROM sampah WHERE status = 'selesai'")->fetchColumn();
    return [
        'monthly' => array_map(static fn(array $row): array => [
            'period' => $row['periode'],
            'periode' => $row['periode'],
            'total' => (int) $row['total'],
        ], $monthly),
        'by_type' => array_map(static fn(array $row): array => [
            'type' => $row['jenis_sampah'],
            'jenis_sampah' => $row['jenis_sampah'],
            'total' => (int) $row['total'],
            'weight_kg' => (float) $row['berat_kg'],
        ], $types),
        'completed_reports' => $completed,
        'total_selesai' => $completed,
    ];
}

function adminUserRows(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT u.id, u.username, u.email, u.nama, u.role, u.aktif, u.dibuat_pada,
                COUNT(s.id) AS total_laporan
         FROM users u LEFT JOIN sampah s ON s.id_user = u.id
         WHERE u.role = 'user'
         GROUP BY u.id ORDER BY u.dibuat_pada DESC"
    )->fetchAll();
    return array_map(static fn(array $row): array => [
        'id' => (int) $row['id'],
        'id_user' => (int) $row['id'],
        'username' => $row['username'],
        'email' => $row['email'],
        'name' => $row['nama'],
        'nama' => $row['nama'],
        'role' => $row['role'],
        'active' => (bool) $row['aktif'],
        'aktif' => (bool) $row['aktif'],
        'total_reports' => (int) $row['total_laporan'],
        'created_at' => $row['dibuat_pada'],
    ], $rows);
}

function adminWorkerRows(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT p.id, p.user_id, p.nama, p.zona_tugas, p.total_ambil, p.status_aktif,
                u.username, u.aktif AS account_active
         FROM petugas p LEFT JOIN users u ON u.id = p.user_id
         ORDER BY p.nama ASC'
    )->fetchAll();
    return array_map(static fn(array $row): array => [
        'id' => (int) $row['id'],
        'user_id' => $row['user_id'] === null ? null : (int) $row['user_id'],
        'name' => (string) $row['nama'],
        'nama' => (string) $row['nama'],
        'zone' => (string) $row['zona_tugas'],
        'zona_tugas' => (string) $row['zona_tugas'],
        'total_pickups' => (int) $row['total_ambil'],
        'total_ambil' => (int) $row['total_ambil'],
        'active' => (bool) $row['status_aktif'] && $row['user_id'] !== null && (bool) ($row['account_active'] ?? false),
        'username' => (string) ($row['username'] ?? ''),
        'has_account' => $row['user_id'] !== null,
    ], $rows);
}

function adminVehicleRows(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT k.*,
                COALESCE((SELECT SUM(s.berat_kg) FROM sampah s
                          WHERE s.kendaraan_id = k.id AND s.status = 'ditugaskan'), 0) AS reserved_kg
         FROM kendaraan k ORDER BY k.plat_nomor ASC"
    )->fetchAll();
    return array_map(static fn(array $row): array => [
        'id' => (int) $row['id'],
        'plate' => (string) $row['plat_nomor'],
        'plat_nomor' => (string) $row['plat_nomor'],
        'capacity_kg' => (float) $row['kapasitas_kg'],
        'load_kg' => (float) $row['muatan_kg'],
        'reserved_kg' => (float) $row['reserved_kg'],
        'condition' => (string) $row['kondisi'],
        'route' => (string) $row['rute'],
        'available_kg' => max(0, (float) $row['kapasitas_kg'] - (float) $row['muatan_kg'] - (float) $row['reserved_kg']),
    ], $rows);
}

function adminTpaRows(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT t.*,
                COALESCE((SELECT SUM(s.berat_kg) / 1000 FROM sampah s
                          WHERE s.tpa_id = t.id AND s.status IN ('ditugaskan', 'diangkut')), 0) AS reserved_ton
         FROM tpa t ORDER BY t.nama ASC"
    )->fetchAll();
    return array_map(static fn(array $row): array => [
        'id' => (int) $row['id'],
        'name' => (string) $row['nama'],
        'address' => (string) $row['alamat'],
        'capacity_ton' => (float) $row['kapasitas_ton'],
        'filled_ton' => (float) $row['terisi_ton'],
        'reserved_ton' => (float) $row['reserved_ton'],
        'last_emptied' => (string) ($row['tgl_terakhir_dikosongkan'] ?? ''),
        'remaining_ton' => max(0, (float) $row['kapasitas_ton'] - (float) $row['terisi_ton'] - (float) $row['reserved_ton']),
    ], $rows);
}

function workerProfile(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM petugas WHERE user_id = ? AND status_aktif = 1 LIMIT 1');
    $stmt->execute([$userId]);
    $worker = $stmt->fetch();
    return $worker ?: null;
}

function intSegment(?string $value): ?int
{
    return $value !== null && ctype_digit($value) ? (int) $value : null;
}

try {
    $pdo = db();
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $parts = segments();
    $root = $parts[0] ?? '';
    $second = $parts[1] ?? '';
    $third = $parts[2] ?? '';
    $payload = body();

    if ($root === '' || $root === 'health') {
        respond(['status' => 'ok', 'database' => 'connected', 'service' => 'Smart Waste REST API']);
    }

    if ($root === 'auth' && $second === 'google') {
        fail('Login Google tidak digunakan. Masuk memakai username dan kata sandi.', 410);
    }

    if ($root === 'auth' && $second === 'login' && $method === 'POST') {
        $client = strtolower(trim((string) ($payload['client'] ?? 'web')));
        if (!in_array($client, ['web', 'android'], true)) {
            fail('Client tidak dikenal.', 422);
        }
        $allowedRoles = $client === 'android' ? ['admin', 'petugas'] : ['user'];
        $identity = trim((string) ($payload['username'] ?? $payload['email'] ?? $payload['login'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        if ($identity === '' || $password === '') {
            fail('Username/email dan password wajib diisi.', 422);
        }
        $stmt = $pdo->prepare(
            'SELECT id, username, email, password_hash, nama, role, aktif, is_super_admin FROM users
             WHERE (username = ? OR email = ?) LIMIT 1'
        );
        $stmt->execute([$identity, $identity]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            fail('Username atau password salah.', 401);
        }
        if (!(int) $user['aktif']) {
            fail('Akun ini sedang dinonaktifkan oleh admin.', 403);
        }
        if (!in_array($user['role'], $allowedRoles, true)) {
            fail($client === 'android'
                ? 'Login Android hanya untuk admin atau petugas.'
                : 'Login website hanya untuk user.', 403);
        }
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 7);
        $pdo->prepare('DELETE FROM auth_tokens WHERE user_id = ? OR expires_at <= NOW()')->execute([$user['id']]);
        $pdo->prepare(
            'INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
        )->execute([$user['id'], hash('sha256', $token), $expires]);
        respond(['message' => 'Login berhasil.', 'token' => $token, 'expires_at' => $expires, 'user' => publicUser($user)]);
    }

    if ($root === 'auth' && $second === 'register' && $method === 'POST') {
        $client = strtolower(trim((string) ($payload['client'] ?? 'web')));
        if ($client !== 'web') {
            fail('Pendaftaran hanya tersedia di website untuk user.', 403);
        }
        $name = trim((string) ($payload['name'] ?? $payload['nama'] ?? ''));
        $username = trim((string) ($payload['username'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        if ($name === '' || $username === '' || $password === '') {
            fail('Nama, username, dan password wajib diisi.', 422);
        }
        if (strlen($username) < 3 || strlen($password) < 5) {
            fail('Username minimal 3 karakter dan password minimal 5 karakter.', 422);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fail('Format email tidak valid.', 422);
        }
        $duplicate = $pdo->prepare('SELECT id FROM users WHERE username = ? OR (? <> "" AND email = ?) LIMIT 1');
        $duplicate->execute([$username, $email, $email]);
        if ($duplicate->fetch()) {
            fail('Username atau email sudah digunakan.', 409);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, nama, role, aktif) VALUES (?, ?, ?, ?, "user", 1)'
        );
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $name]);
        $id = (int) $pdo->lastInsertId();
        $newUser = $pdo->prepare('SELECT id, username, email, nama, role, aktif FROM users WHERE id = ?');
        $newUser->execute([$id]);
        $user = $newUser->fetch();
        respond(['message' => 'Registrasi user berhasil.', 'user' => publicUser($user)], 201);
    }

    if ($root === 'auth' && $second === 'logout' && $method === 'POST') {
        if (bearerToken()) {
            $pdo->prepare('DELETE FROM auth_tokens WHERE token_hash = ?')->execute([hash('sha256', bearerToken())]);
        }
        respond(['message' => 'Logout berhasil.']);
    }

    if ($root === 'me' && $method === 'GET') {
        $user = requireUser($pdo);
        respond(['user' => publicUser($user)]);
    }

    if ($root === 'me' && $method === 'PUT') {
        $user = requireUser($pdo);
        $name = trim((string) ($payload['name'] ?? $payload['nama'] ?? $user['nama']));
        $email = strtolower(trim((string) ($payload['email'] ?? $user['email'] ?? '')));
        if ($name === '') {
            fail('Nama tidak boleh kosong.', 422);
        }
        if (in_array($user['role'], ['admin', 'petugas'], true)
            && strcasecmp($email, (string) ($user['email'] ?? '')) !== 0) {
            fail('Email akun staf hanya dapat diubah oleh admin pertama melalui pendaftaran akun.', 403);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fail('Format email tidak valid.', 422);
        }
        $duplicate = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = ? AND id <> ? LIMIT 1');
        $duplicate->execute([$email, $user['id']]);
        if ($email !== '' && $duplicate->fetch()) {
            fail('Email sudah digunakan akun lain.', 409);
        }
        $pdo->prepare('UPDATE users SET nama = ?, email = ? WHERE id = ?')->execute([$name, $email, $user['id']]);
        $fresh = $pdo->prepare('SELECT id, username, email, nama, role, aktif FROM users WHERE id = ?');
        $fresh->execute([$user['id']]);
        respond(['message' => 'Profil diperbarui.', 'user' => publicUser($fresh->fetch())]);
    }

    if ($root === 'me' && ($second === 'password' || $second === 'change-password') && in_array($method, ['PUT', 'PATCH'], true)) {
        $user = requireUser($pdo);
        $old = (string) ($payload['old_password'] ?? $payload['current_password'] ?? '');
        $new = (string) ($payload['new_password'] ?? $payload['password'] ?? '');
        if ($old === '' || $new === '' || strlen($new) < 5) {
            fail('Password lama dan password baru minimal 5 karakter wajib diisi.', 422);
        }
        $hash = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $hash->execute([$user['id']]);
        if (!password_verify($old, (string) $hash->fetchColumn())) {
            fail('Password lama salah.', 422);
        }
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        $pdo->prepare('DELETE FROM auth_tokens WHERE user_id = ?')->execute([$user['id']]);
        respond(['message' => 'Password berhasil diganti. Silakan login kembali.']);
    }

    // Endpoint laporan user. Jika dipanggil admin, GET menampilkan semua laporan.
    if (($root === 'sampah' || $root === 'laporan') && $second === '' && $method === 'GET') {
        $user = requireUser($pdo);
        $status = normalizeStatus((string) ($_GET['status'] ?? ''));
        $search = isset($_GET['q']) ? (string) $_GET['q'] : null;
        $rows = reportQuery($pdo, $user['role'] === 'user' ? (int) $user['id'] : null, $status, $search);
        $reports = array_map(fn(array $row): array => reportRow($pdo, $row), $rows);
        respond(['reports' => $reports, 'data' => $reports]);
    }

    if (($root === 'sampah' || $root === 'laporan') && ($second === 'mine' || $second === 'saya') && $method === 'GET') {
        $user = requireUser($pdo, 'user');
        $status = normalizeStatus((string) ($_GET['status'] ?? ''));
        $rows = reportQuery($pdo, (int) $user['id'], $status);
        $reports = array_map(fn(array $row): array => reportRow($pdo, $row, true), $rows);
        respond(['reports' => $reports, 'data' => $reports]);
    }

    if (($root === 'sampah' || $root === 'laporan') && $second === '' && $method === 'POST') {
        $user = requireUser($pdo, 'user');
        $type = trim((string) ($payload['jenis_sampah'] ?? $payload['jenis'] ?? $payload['type'] ?? ''));
        $location = trim((string) ($payload['lokasi'] ?? $payload['location'] ?? ''));
        $description = trim((string) ($payload['deskripsi'] ?? $payload['description'] ?? ''));
        $weight = (float) ($payload['berat_kg'] ?? $payload['weight_kg'] ?? $payload['weight'] ?? 0);
        if ($type === '' || $location === '') {
            fail('Jenis sampah dan lokasi wajib diisi.', 422);
        }
        if ($weight < 0 || $weight > 100000) {
            fail('Berat sampah tidak valid.', 422);
        }
        $photo = saveUploadedPhoto();
        $code = newReportCode($pdo);
        $date = trim((string) ($payload['tanggal_lapor'] ?? date('Y-m-d')));
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO sampah (kode_laporan, jenis, berat_kg, foto, lokasi, deskripsi, tanggal_lapor, status, id_user)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "menunggu_verifikasi", ?)'
            );
            $stmt->execute([$code, $type, $weight, $photo, $location, $description, $date, $user['id']]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO riwayat_status (sampah_id, status, diubah_oleh, catatan) VALUES (?, "menunggu_verifikasi", NULL, ?)'
            )->execute([$id, 'Laporan dibuat oleh user']);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        $report = findReport($pdo, $id);
        respond(['message' => 'Laporan berhasil dikirim dan menunggu verifikasi.', 'report' => reportRow($pdo, $report, true)], 201);
    }

    $reportId = intSegment($second);
    if (($root === 'sampah' || $root === 'laporan') && $reportId !== null && $third === '' && $method === 'GET') {
        $user = requireUser($pdo);
        $report = findReport($pdo, $reportId);
        if (!$report) {
            fail('Laporan tidak ditemukan.', 404);
        }
        if ($user['role'] === 'user' && (int) $report['id_user'] !== (int) $user['id']) {
            fail('Laporan ini bukan milik akun tersebut.', 403);
        }
        respond(['report' => reportRow($pdo, $report, true)]);
    }

    if (($root === 'sampah' || $root === 'laporan') && $reportId !== null && $third === 'riwayat' && $method === 'GET') {
        $user = requireUser($pdo);
        $report = findReport($pdo, $reportId);
        if (!$report) {
            fail('Laporan tidak ditemukan.', 404);
        }
        if ($user['role'] === 'user' && (int) $report['id_user'] !== (int) $user['id']) {
            fail('Laporan ini bukan milik akun tersebut.', 403);
        }
        respond(['history' => statusHistory($pdo, $reportId), 'riwayat' => statusHistory($pdo, $reportId)]);
    }

    if ($root === 'admin' && $second === 'petugas' && $third === '' && $method === 'GET') {
        requireUser($pdo, 'admin');
        $workers = adminWorkerRows($pdo);
        respond(['workers' => $workers, 'data' => $workers]);
    }

    if ($root === 'admin' && in_array($second, ['petugas', 'akun'], true) && $third === '' && $method === 'POST') {
        $creator = requireUser($pdo, 'admin');
        if (!(bool) ($creator['is_super_admin'] ?? false)) {
            fail('Hanya admin pertama yang dapat mendaftarkan akun Admin/Petugas.', 403);
        }
        $name = trim((string) ($payload['nama'] ?? $payload['name'] ?? ''));
        $username = trim((string) ($payload['username'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $zone = trim((string) ($payload['zona_tugas'] ?? $payload['zone'] ?? ''));
        $role = $second === 'petugas' ? 'petugas' : strtolower(trim((string) ($payload['role'] ?? '')));
        if (!in_array($role, ['admin', 'petugas'], true)) {
            fail('Role akun harus Admin atau Petugas.', 422);
        }
        if ($name === '' || strlen($username) < 3 || strlen($password) < 5 || $email === '') {
            fail('Nama, username minimal 3 karakter, email, dan password awal minimal 5 karakter wajib diisi.', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fail('Format email tidak valid.', 422);
        }
        $duplicate = $pdo->prepare('SELECT id FROM users WHERE username = ? OR LOWER(email) = ? LIMIT 1');
        $duplicate->execute([$username, $email]);
        if ($duplicate->fetch()) {
            fail('Username atau email sudah digunakan.', 409);
        }
        $pdo->beginTransaction();
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (username, email, password_hash, nama, role, aktif, is_super_admin) VALUES (?, ?, ?, ?, ?, 1, 0)')
                ->execute([$username, $email, $passwordHash, $name, $role]);
            $userId = (int) $pdo->lastInsertId();
            if ($role === 'petugas') {
                $pdo->prepare('INSERT INTO petugas (user_id, nama, zona_tugas, status_aktif) VALUES (?, ?, ?, 1)')
                    ->execute([$userId, $name, $zone]);
            } else {
                $pdo->prepare('INSERT INTO admin (user_id, nama_admin, email, password_hash, aktif) VALUES (?, ?, ?, ?, 1)')
                    ->execute([$userId, $name, $email, $passwordHash]);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        respond(['message' => 'Akun ' . ucfirst($role) . ' berhasil dibuat. Login memakai username dan kata sandi.'], 201);
    }

    $staffId = $root === 'admin' && $second === 'petugas' ? intSegment($third) : null;
    if ($staffId !== null && $method === 'PATCH' && ($parts[3] ?? '') === '') {
        requireUser($pdo, 'admin');
        $stmt = $pdo->prepare('SELECT id, user_id, status_aktif FROM petugas WHERE id = ? LIMIT 1');
        $stmt->execute([$staffId]);
        $worker = $stmt->fetch();
        if (!$worker) {
            fail('Data petugas tidak ditemukan.', 404);
        }
        $active = array_key_exists('aktif', $payload)
            ? (bool) $payload['aktif']
            : (array_key_exists('active', $payload) ? (bool) $payload['active'] : (bool) $worker['status_aktif']);
        $zone = trim((string) ($payload['zona_tugas'] ?? $payload['zone'] ?? ''));
        if (!$active) {
            $openTasks = $pdo->prepare('SELECT COUNT(*) FROM sampah WHERE petugas_id = ? AND status IN ("ditugaskan", "diangkut")');
            $openTasks->execute([$staffId]);
            if ((int) $openTasks->fetchColumn() > 0) {
                fail('Petugas masih memiliki penugasan aktif. Selesaikan atau alihkan tugas terlebih dahulu.', 409);
            }
        }
        $pdo->prepare('UPDATE petugas SET status_aktif = ?, zona_tugas = ? WHERE id = ?')
            ->execute([$active ? 1 : 0, $zone, $staffId]);
        if ($worker['user_id'] !== null) {
            $pdo->prepare('UPDATE users SET aktif = ? WHERE id = ?')->execute([$active ? 1 : 0, $worker['user_id']]);
            if (!$active) {
                $pdo->prepare('DELETE FROM auth_tokens WHERE user_id = ?')->execute([$worker['user_id']]);
            }
        }
        respond(['message' => $active ? 'Petugas diaktifkan.' : 'Petugas dinonaktifkan.', 'workers' => adminWorkerRows($pdo)]);
    }

    if (($root === 'dashboard' || ($root === 'admin' && $second === 'dashboard')) && $method === 'GET') {
        requireUser($pdo, 'admin');
        $summary = dashboard($pdo);
        respond(['dashboard' => $summary, 'data' => $summary]);
    }

    if ($root === 'admin' && $second === 'kendaraan' && $third === '' && $method === 'GET') {
        requireUser($pdo, 'admin');
        $vehicles = adminVehicleRows($pdo);
        respond(['vehicles' => $vehicles, 'data' => $vehicles]);
    }
    if ($root === 'admin' && $second === 'kendaraan' && $third === '' && $method === 'POST') {
        requireUser($pdo, 'admin');
        $plate = strtoupper(trim((string) ($payload['plat_nomor'] ?? $payload['plate'] ?? '')));
        $capacity = (float) ($payload['kapasitas_kg'] ?? $payload['capacity_kg'] ?? 0);
        if ($plate === '' || $capacity <= 0) {
            fail('Nomor kendaraan dan kapasitas di atas 0 kg wajib diisi.', 422);
        }
        try {
            $condition = trim((string) ($payload['kondisi'] ?? $payload['condition'] ?? 'Baik'));
            $route = trim((string) ($payload['rute'] ?? $payload['route'] ?? ''));
            $pdo->prepare('INSERT INTO kendaraan (plat_nomor, kapasitas_kg, kondisi, rute) VALUES (?, ?, ?, ?)')
                ->execute([$plate, $capacity, $condition, $route]);
        } catch (PDOException $error) {
            if ((string) $error->getCode() === '23000') {
                fail('Nomor kendaraan sudah terdaftar.', 409);
            }
            throw $error;
        }
        respond(['message' => 'Kendaraan berhasil ditambahkan.', 'vehicles' => adminVehicleRows($pdo)], 201);
    }
    $vehicleId = $root === 'admin' && $second === 'kendaraan' ? intSegment($third) : null;
    if ($vehicleId !== null && $method === 'PATCH' && ($parts[3] ?? '') === '') {
        requireUser($pdo, 'admin');
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM kendaraan WHERE id = ? FOR UPDATE');
            $stmt->execute([$vehicleId]);
            $vehicle = $stmt->fetch();
            if (!$vehicle) {
                $pdo->rollBack();
                fail('Kendaraan tidak ditemukan.', 404);
            }
            $plate = strtoupper(trim((string) ($payload['plat_nomor'] ?? $payload['plate'] ?? $vehicle['plat_nomor'])));
            $capacity = (float) ($payload['kapasitas_kg'] ?? $payload['capacity_kg'] ?? $vehicle['kapasitas_kg']);
            $reserved = $pdo->prepare('SELECT COALESCE(SUM(berat_kg), 0) FROM sampah WHERE kendaraan_id = ? AND status = "ditugaskan"');
            $reserved->execute([$vehicleId]);
            $reservedKg = (float) $reserved->fetchColumn();
            if ($plate === '' || $capacity <= 0 || $capacity < (float) $vehicle['muatan_kg'] + $reservedKg) {
                $pdo->rollBack();
                fail('Plat wajib diisi dan kapasitas harus cukup untuk muatan serta penugasan aktif.', 422);
            }
            $condition = trim((string) ($payload['kondisi'] ?? $payload['condition'] ?? 'Baik'));
            $route = trim((string) ($payload['rute'] ?? $payload['route'] ?? ''));
            if (strtolower($condition) !== 'baik') {
                $activeTasks = $pdo->prepare('SELECT COUNT(*) FROM sampah WHERE kendaraan_id = ? AND status IN ("ditugaskan", "diangkut")');
                $activeTasks->execute([$vehicleId]);
                if ((int) $activeTasks->fetchColumn() > 0 || (float) $vehicle['muatan_kg'] > 0) {
                    $pdo->rollBack();
                    fail('Kendaraan yang bermuatan atau memiliki penugasan aktif tidak dapat ditandai servis.', 409);
                }
            }
            $duplicate = $pdo->prepare('SELECT id FROM kendaraan WHERE plat_nomor = ? AND id <> ? LIMIT 1');
            $duplicate->execute([$plate, $vehicleId]);
            if ($duplicate->fetch()) {
                $pdo->rollBack();
                fail('Nomor kendaraan sudah terdaftar.', 409);
            }
            $pdo->prepare('UPDATE kendaraan SET plat_nomor = ?, kapasitas_kg = ?, kondisi = ?, rute = ? WHERE id = ?')
                ->execute([$plate, $capacity, $condition, $route, $vehicleId]);
            $pdo->commit();
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ((string) $error->getCode() === '23000') {
                fail('Nomor kendaraan sudah terdaftar.', 409);
            }
            throw $error;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        respond(['message' => 'Data kendaraan diperbarui.', 'vehicles' => adminVehicleRows($pdo)]);
    }

    if ($root === 'admin' && $second === 'tpa' && $third === '' && $method === 'GET') {
        requireUser($pdo, 'admin');
        $facilities = adminTpaRows($pdo);
        respond(['tpa' => $facilities, 'data' => $facilities]);
    }
    if ($root === 'admin' && $second === 'tpa' && $third === '' && $method === 'POST') {
        requireUser($pdo, 'admin');
        $name = trim((string) ($payload['nama'] ?? $payload['name'] ?? ''));
        $address = trim((string) ($payload['alamat'] ?? $payload['address'] ?? ''));
        $capacity = (float) ($payload['kapasitas_ton'] ?? $payload['capacity_ton'] ?? 0);
        if ($name === '' || $capacity <= 0) {
            fail('Nama TPA dan kapasitas di atas 0 ton wajib diisi.', 422);
        }
        $pdo->prepare('INSERT INTO tpa (nama, alamat, kapasitas_ton) VALUES (?, ?, ?)')->execute([$name, $address, $capacity]);
        respond(['message' => 'Data TPA berhasil ditambahkan.', 'tpa' => adminTpaRows($pdo)], 201);
    }
    $tpaId = $root === 'admin' && $second === 'tpa' ? intSegment($third) : null;
    if ($tpaId !== null && $method === 'PATCH' && ($parts[3] ?? '') === '') {
        requireUser($pdo, 'admin');
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT terisi_ton FROM tpa WHERE id = ? FOR UPDATE');
            $stmt->execute([$tpaId]);
            $facility = $stmt->fetch();
            if (!$facility) {
                $pdo->rollBack();
                fail('Data TPA tidak ditemukan.', 404);
            }
            $name = trim((string) ($payload['nama'] ?? $payload['name'] ?? ''));
            $address = trim((string) ($payload['alamat'] ?? $payload['address'] ?? ''));
            $capacity = (float) ($payload['kapasitas_ton'] ?? $payload['capacity_ton'] ?? 0);
            $reserved = $pdo->prepare('SELECT COALESCE(SUM(berat_kg), 0) FROM sampah WHERE tpa_id = ? AND status IN ("ditugaskan", "diangkut")');
            $reserved->execute([$tpaId]);
            $reservedTon = (float) $reserved->fetchColumn() / 1000;
            if ($name === '' || $capacity <= 0 || $capacity < (float) $facility['terisi_ton'] + $reservedTon) {
                $pdo->rollBack();
                fail('Nama wajib diisi dan kapasitas harus cukup untuk isi serta penugasan aktif TPA.', 422);
            }
            $pdo->prepare('UPDATE tpa SET nama = ?, alamat = ?, kapasitas_ton = ? WHERE id = ?')->execute([$name, $address, $capacity, $tpaId]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        respond(['message' => 'Data TPA diperbarui.', 'tpa' => adminTpaRows($pdo)]);
    }

    if ($root === 'admin' && $second === 'laporan-harian' && $third === '' && $method === 'GET') {
        requireUser($pdo, 'admin');
        $from = trim((string) ($_GET['from'] ?? date('Y-m-d', strtotime('-29 days'))));
        $to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $from) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $to) || $from > $to) {
            fail('Rentang tanggal laporan tidak valid.', 422);
        }
        $stmt = $pdo->prepare(
            "SELECT DATE(selesai_pada) AS tanggal, COUNT(*) AS total_sampah,
                    COALESCE(SUM(berat_kg), 0) AS total_berat_kg,
                    SUM(CASE WHEN UPPER(tingkat_bahaya) IN ('TINGGI', 'B3', 'BERBAHAYA', 'HIGH', 'CRITICAL') THEN 1 ELSE 0 END) AS peringatan_b3
             FROM sampah WHERE status = 'selesai' AND DATE(selesai_pada) BETWEEN ? AND ?
             GROUP BY DATE(selesai_pada) ORDER BY tanggal DESC"
        );
        $stmt->execute([$from, $to]);
        $daily = array_map(static fn(array $row): array => [
            'date' => (string) $row['tanggal'],
            'total_waste' => (int) $row['total_sampah'],
            'weight_kg' => (float) $row['total_berat_kg'],
            'b3_alerts' => (int) $row['peringatan_b3'],
        ], $stmt->fetchAll());
        respond(['daily_reports' => $daily, 'data' => $daily, 'from' => $from, 'to' => $to]);
    }

    if (($root === 'admin' && ($second === 'laporan' || $second === 'reports')) && $third === '' && $method === 'GET') {
        requireUser($pdo, 'admin');
        $status = normalizeStatus((string) ($_GET['status'] ?? ''));
        $search = isset($_GET['q']) ? (string) $_GET['q'] : null;
        $rows = reportQuery($pdo, null, $status, $search);
        $reports = array_map(fn(array $row): array => reportRow($pdo, $row), $rows);
        respond(['reports' => $reports, 'data' => $reports]);
    }

    $adminReportId = intSegment($third);

    if ($root === 'petugas' && $second === 'tugas' && $third === '' && $method === 'GET') {
        $user = requireUser($pdo, 'petugas');
        $worker = workerProfile($pdo, (int) $user['id']);
        if (!$worker) {
            fail('Akun belum terhubung ke data petugas aktif. Hubungi admin.', 403);
        }
        $rows = reportQuery($pdo, null, null, null, (int) $worker['id']);
        $rows = array_values(array_filter($rows, static fn(array $row): bool =>
            in_array((string) $row['status'], ['ditugaskan', 'diangkut', 'selesai'], true)
        ));
        $tasks = array_map(fn(array $row): array => reportRow($pdo, $row, true), $rows);
        respond(['tasks' => $tasks, 'data' => $tasks, 'worker' => [
            'id' => (int) $worker['id'], 'name' => (string) $worker['nama'],
            'zone' => (string) $worker['zona_tugas'], 'total_pickups' => (int) $worker['total_ambil'],
        ]]);
    }

    $taskId = $root === 'petugas' && $second === 'tugas' ? intSegment($third) : null;
    if ($taskId !== null && in_array($parts[3] ?? '', ['diangkut', 'selesai'], true) && $method === 'PATCH') {
        $user = requireUser($pdo, 'petugas');
        $worker = workerProfile($pdo, (int) $user['id']);
        if (!$worker) {
            fail('Akun belum terhubung ke data petugas aktif. Hubungi admin.', 403);
        }
        $action = (string) $parts[3];
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM sampah WHERE id = ? FOR UPDATE');
            $stmt->execute([$taskId]);
            $report = $stmt->fetch();
            if (!$report) {
                $pdo->rollBack();
                fail('Penugasan tidak ditemukan.', 404);
            }
            if ((int) ($report['petugas_id'] ?? 0) !== (int) $worker['id']) {
                $pdo->rollBack();
                fail('Penugasan ini bukan milik akun petugas tersebut.', 403);
            }
            $weight = (float) $report['berat_kg'];
            if ($action === 'diangkut') {
                if ($report['status'] !== 'ditugaskan') {
                    $pdo->rollBack();
                    fail('Sampah hanya dapat ditandai diangkut setelah penugasan.', 409);
                }
                $vehicleId = (int) ($report['kendaraan_id'] ?? 0);
                $stmt = $pdo->prepare('SELECT kapasitas_kg, muatan_kg, kondisi FROM kendaraan WHERE id = ? FOR UPDATE');
                $stmt->execute([$vehicleId]);
                $vehicle = $stmt->fetch();
                if (!$vehicle || strtolower((string) $vehicle['kondisi']) !== 'baik'
                    || (float) $vehicle['muatan_kg'] + $weight > (float) $vehicle['kapasitas_kg']) {
                    $pdo->rollBack();
                    fail('Kendaraan tidak tersedia atau kapasitas muatnya tidak cukup.', 409);
                }
                $pdo->prepare('UPDATE kendaraan SET muatan_kg = muatan_kg + ? WHERE id = ?')->execute([$weight, $vehicleId]);
                $pdo->prepare('UPDATE sampah SET status = "diangkut", diambil_pada = NOW() WHERE id = ?')->execute([$taskId]);
                $note = 'Petugas mulai mengangkut sampah.';
                $nextStatus = 'diangkut';
            } else {
                if ($report['status'] !== 'diangkut') {
                    $pdo->rollBack();
                    fail('Penugasan harus berstatus diangkut sebelum diselesaikan.', 409);
                }
                $vehicleId = (int) ($report['kendaraan_id'] ?? 0);
                $tpaId = (int) ($report['tpa_id'] ?? 0);
                $stmt = $pdo->prepare('SELECT id FROM petugas WHERE id = ? FOR UPDATE');
                $stmt->execute([$worker['id']]);
                if (!$stmt->fetch()) {
                    $pdo->rollBack();
                    fail('Data petugas tidak ditemukan.', 404);
                }
                $stmt = $pdo->prepare('SELECT muatan_kg FROM kendaraan WHERE id = ? FOR UPDATE');
                $stmt->execute([$vehicleId]);
                $vehicle = $stmt->fetch();
                $stmt = $pdo->prepare('SELECT kapasitas_ton, terisi_ton FROM tpa WHERE id = ? FOR UPDATE');
                $stmt->execute([$tpaId]);
                $facility = $stmt->fetch();
                if (!$vehicle || !$facility || (float) $facility['terisi_ton'] + ($weight / 1000) > (float) $facility['kapasitas_ton']) {
                    $pdo->rollBack();
                    fail('TPA tidak tersedia atau kapasitasnya sudah penuh.', 409);
                }
                $pdo->prepare('UPDATE kendaraan SET muatan_kg = GREATEST(0, muatan_kg - ?) WHERE id = ?')->execute([$weight, $vehicleId]);
                $pdo->prepare('UPDATE tpa SET terisi_ton = terisi_ton + ? WHERE id = ?')->execute([$weight / 1000, $tpaId]);
                $pdo->prepare('UPDATE petugas SET total_ambil = total_ambil + 1 WHERE id = ?')->execute([$worker['id']]);
                $pdo->prepare('UPDATE sampah SET status = "selesai", sudah_diambil = 1, selesai_pada = NOW() WHERE id = ?')->execute([$taskId]);
                $note = 'Sampah diterima di TPA dan penugasan diselesaikan.';
                $nextStatus = 'selesai';
            }
            $pdo->prepare('INSERT INTO riwayat_status (sampah_id, status, diubah_oleh, catatan) VALUES (?, ?, ?, ?)')
                ->execute([$taskId, $nextStatus, $user['id'], $note]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        $updated = findReport($pdo, $taskId);
        $message = $action === 'diangkut'
            ? 'Status penugasan diperbarui.'
            : 'Pengangkutan selesai.';
        respond([
            'message' => $message,
            'report' => reportRow($pdo, $updated, true),
        ]);
    }

    if ($root === 'admin' && in_array($second, ['laporan', 'reports'], true)
        && $adminReportId !== null && ($parts[3] ?? '') === 'tugaskan' && $method === 'PATCH') {
        $admin = requireUser($pdo, 'admin');
        $workerId = (int) ($payload['worker_id'] ?? $payload['petugas_id'] ?? 0);
        $vehicleId = (int) ($payload['vehicle_id'] ?? $payload['kendaraan_id'] ?? 0);
        $tpaId = (int) ($payload['tpa_id'] ?? 0);
        if ($workerId < 1 || $vehicleId < 1 || $tpaId < 1) {
            fail('Petugas, kendaraan, dan TPA harus dipilih.', 422);
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM sampah WHERE id = ? FOR UPDATE');
            $stmt->execute([$adminReportId]);
            $report = $stmt->fetch();
            if (!$report) {
                $pdo->rollBack();
                fail('Laporan tidak ditemukan.', 404);
            }
            if ($report['status'] !== 'menunggu_verifikasi') {
                $pdo->rollBack();
                fail('Hanya laporan menunggu verifikasi yang dapat ditugaskan.', 409);
            }
            $stmt = $pdo->prepare('SELECT id, user_id, status_aktif FROM petugas WHERE id = ? FOR UPDATE');
            $stmt->execute([$workerId]);
            $worker = $stmt->fetch();
            if (!$worker || !(int) $worker['status_aktif'] || $worker['user_id'] === null) {
                $pdo->rollBack();
                fail('Pilih petugas aktif yang sudah memiliki akun aplikasi.', 422);
            }
            $stmt = $pdo->prepare('SELECT aktif FROM users WHERE id = ? AND role = "petugas" LIMIT 1');
            $stmt->execute([$worker['user_id']]);
            if (!(int) $stmt->fetchColumn()) {
                $pdo->rollBack();
                fail('Akun petugas sedang nonaktif.', 422);
            }
            $stmt = $pdo->prepare('SELECT kapasitas_kg, muatan_kg, kondisi FROM kendaraan WHERE id = ? FOR UPDATE');
            $stmt->execute([$vehicleId]);
            $vehicle = $stmt->fetch();
            if (!$vehicle || strtolower((string) $vehicle['kondisi']) !== 'baik') {
                $pdo->rollBack();
                fail('Pilih kendaraan dengan kondisi baik.', 422);
            }
            $stmt = $pdo->prepare('SELECT kapasitas_ton, terisi_ton FROM tpa WHERE id = ? FOR UPDATE');
            $stmt->execute([$tpaId]);
            $facility = $stmt->fetch();
            if (!$facility) {
                $pdo->rollBack();
                fail('Data TPA tidak ditemukan.', 422);
            }
            $weight = (float) $report['berat_kg'];
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(berat_kg), 0) FROM sampah WHERE kendaraan_id = ? AND status = "ditugaskan"');
            $stmt->execute([$vehicleId]);
            $reservedVehicleKg = (float) $stmt->fetchColumn();
            if ((float) $vehicle['muatan_kg'] + $reservedVehicleKg + $weight > (float) $vehicle['kapasitas_kg']) {
                $pdo->rollBack();
                fail('Muatan kendaraan tidak cukup untuk laporan ini.', 409);
            }
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(berat_kg), 0) FROM sampah WHERE tpa_id = ? AND status IN ("ditugaskan", "diangkut")');
            $stmt->execute([$tpaId]);
            $reservedTpaKg = (float) $stmt->fetchColumn();
            if ((float) $facility['terisi_ton'] + (($reservedTpaKg + $weight) / 1000) > (float) $facility['kapasitas_ton']) {
                $pdo->rollBack();
                fail('Sisa kapasitas TPA tidak cukup untuk laporan ini.', 409);
            }
            $pdo->prepare(
                'UPDATE sampah SET status = "ditugaskan", '
                . 'petugas_id = ?, kendaraan_id = ?, tpa_id = ?, '
                . 'ditugaskan_pada = NOW() WHERE id = ?'
            )
                ->execute([$workerId, $vehicleId, $tpaId, $adminReportId]);
            $pdo->prepare('INSERT INTO riwayat_status (sampah_id, status, diubah_oleh, catatan) VALUES (?, "ditugaskan", ?, ?)')
                ->execute([
                    $adminReportId,
                    $admin['id'],
                    'Ditugaskan kepada petugas #' . $workerId
                        . ', kendaraan #' . $vehicleId . ', TPA #' . $tpaId,
                ]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
        $updated = findReport($pdo, $adminReportId);
        respond(['message' => 'Laporan berhasil ditugaskan.', 'report' => reportRow($pdo, $updated, true)]);
    }

    if ($root === 'admin' && in_array($second, ['laporan', 'reports'], true) && $adminReportId !== null && $method === 'GET') {
        requireUser($pdo, 'admin');
        $report = findReport($pdo, $adminReportId);
        if (!$report) {
            fail('Laporan tidak ditemukan.', 404);
        }
        respond(['report' => reportRow($pdo, $report, true)]);
    }

    if (($root === 'admin' && in_array($second, ['laporan', 'reports'], true) && $third !== '' && $parts[3] === 'status')
        || (($root === 'laporan' || $root === 'sampah') && $reportId !== null && $third === 'status')) {
        if (!in_array($method, ['PUT', 'PATCH', 'POST'], true)) {
            fail('Method tidak diizinkan.', 405);
        }
        $admin = requireUser($pdo, 'admin');
        $id = $adminReportId ?? $reportId;
        $nextStatus = normalizeStatus((string) ($payload['status'] ?? ''));
        if ($nextStatus === null) {
            fail('Status laporan tidak valid.', 422);
        }
        $report = findReport($pdo, (int) $id);
        if (!$report) {
            fail('Laporan tidak ditemukan.', 404);
        }
        $currentStatus = (string) $report['status'];
        $legacyUnassignedCompletion = $currentStatus === 'diproses'
            && $nextStatus === 'selesai'
            && $report['petugas_id'] === null
            && $report['kendaraan_id'] === null
            && $report['tpa_id'] === null;
        if (!(($currentStatus === 'menunggu_verifikasi' && $nextStatus === 'ditolak') || $legacyUnassignedCompletion)) {
            fail('Gunakan alur verifikasi, penugasan, lalu aksi petugas agar kapasitas dan riwayat tetap akurat.', 409);
        }
        $note = trim((string) ($payload['note'] ?? $payload['catatan'] ?? ''));
        $pdo->beginTransaction();
        try {
            $finishedAt = $nextStatus === 'selesai' ? date('Y-m-d H:i:s') : null;
            $pdo->prepare(
                'UPDATE sampah SET status = ?, sudah_diambil = ?, selesai_pada = ? WHERE id = ?'
            )->execute([$nextStatus, $nextStatus === 'selesai' ? 1 : 0, $finishedAt, $id]);
            $pdo->prepare(
                'INSERT INTO riwayat_status (sampah_id, status, diubah_oleh, catatan) VALUES (?, ?, ?, ?)'
            )->execute([$id, $nextStatus, $admin['id'], $note]);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        $updated = findReport($pdo, (int) $id);
        respond(['message' => 'Status laporan diperbarui.', 'report' => reportRow($pdo, $updated, true)]);
    }

    if (($root === 'users' && $second === '' && $method === 'GET')
        || ($root === 'admin' && $second === 'users' && $third === '' && $method === 'GET')) {
        requireUser($pdo, 'admin');
        $users = adminUserRows($pdo);
        respond(['users' => $users, 'data' => $users]);
    }

    $userId = intSegment($third);
    if ($root === 'admin' && $second === 'users' && $userId !== null && in_array($parts[3] ?? '', ['toggle', 'status'], true)
        && in_array($method, ['PUT', 'PATCH', 'POST'], true)) {
        $admin = requireUser($pdo, 'admin');
        if ($userId === (int) $admin['id']) {
            fail('Akun admin yang sedang dipakai tidak dapat dinonaktifkan.', 422);
        }
        $target = $pdo->prepare("SELECT id, role, aktif FROM users WHERE id = ? LIMIT 1");
        $target->execute([$userId]);
        $targetUser = $target->fetch();
        if (!$targetUser || $targetUser['role'] !== 'user') {
            fail('User tidak ditemukan.', 404);
        }
        $active = array_key_exists('aktif', $payload) ? (bool) $payload['aktif'] : !(bool) $targetUser['aktif'];
        $pdo->prepare('UPDATE users SET aktif = ? WHERE id = ?')->execute([$active ? 1 : 0, $userId]);
        $pdo->prepare('DELETE FROM auth_tokens WHERE user_id = ?')->execute([$userId]);
        respond(['message' => $active ? 'Akun user diaktifkan.' : 'Akun user dinonaktifkan.', 'active' => $active]);
    }

    if (($root === 'admin' && in_array($second, ['statistik', 'statistics'], true) && $third === '')
        || (in_array($root, ['statistik', 'statistics'], true) && $second === '')) {
        requireUser($pdo, 'admin');
        $stats = statistics($pdo);
        respond(['statistics' => $stats, 'data' => $stats]);
    }

    fail('Endpoint tidak ditemukan.', 404);
} catch (PDOException $error) {
    respond(['message' => 'Database error.', 'detail' => $error->getMessage()], 500);
} catch (Throwable $error) {
    respond(['message' => 'Terjadi kesalahan pada server.', 'detail' => $error->getMessage()], 500);
}
