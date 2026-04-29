<?php
// ============================================================
//  config/db.php
//  UPDATE: Menambahkan fungsi role helper dan konsistensi session
// ============================================================
define('DB_HOST',     'localhost');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_NAME',     'labmineral');
define('APP_NAME',    'LabMineral Pro');
define('APP_VERSION', '2.0.0');
define('BASE_URL',    '/labmineral-main');

// ── Koneksi PDO ──────────────────────────────────────────────
$pdo = null;
try {
    $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("<div style='font-family:sans-serif;padding:30px;background:#3d1010;color:#e74c3c;
         border-radius:8px;margin:20px'><h2>&#9888; Koneksi Database Gagal</h2>
         <p>".htmlspecialchars($e->getMessage())."</p></div>");
}

// ── Cek login ────────────────────────────────────────────────
function cekLogin() {
    global $pdo;

    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: '.BASE_URL.'/index.php'); 
        exit;
    }

    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("SELECT id, nama, role, status FROM pengguna WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user || $user['status'] !== 'aktif') {
                session_unset();
                session_destroy();
                header('Location: '.BASE_URL.'/index.php');
                exit;
            }

            $_SESSION['nama'] = $user['nama'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_role'] = $user['role'];
        } catch (Exception $e) {
            // Jika struktur tabel belum lengkap saat setup awal, lanjutkan dengan session yang ada.
        }
    }

    $role = normalizeRole($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');
    $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    $allowedClientScripts = ['dashboard.php', 'client_monitoring.php', 'cetak_invoice.php', 'logout.php'];

    if ($role === 'client' && !in_array($currentScript, $allowedClientScripts, true)) {
        header('Location: '.BASE_URL.'/client_monitoring.php');
        exit;
    }
}

// ── Sanitasi ─────────────────────────────────────────────────
function bersihkan($s) {
    return htmlspecialchars(trim((string)$s), ENT_QUOTES, 'UTF-8');
}

// ============================================================
// ROLE HELPER FUNCTIONS (terintegrasi dengan session)
// ============================================================

function normalizeRole($role) {
    return $role === 'klien' ? 'client' : $role;
}

function currentUserRole() {
    return normalizeRole($_SESSION['user_role'] ?? $_SESSION['role'] ?? null);
}

/**
 * Cek apakah user memiliki role tertentu.
 * Role lama "klien" diperlakukan sebagai alias dari role baru "client".
 */
function hasRole($role) {
    $userRole = currentUserRole();

    if (!$userRole) return false;

    if (is_array($role)) {
        return in_array($userRole, array_map('normalizeRole', $role), true);
    }
    return $userRole === normalizeRole($role);
}

/**
 * Cek apakah user adalah admin
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Cek apakah user adalah analis
 */
function isAnalis() {
    return hasRole('analis');
}

/**
 * Cek apakah user adalah supervisor
 */
function isSupervisor() {
    return hasRole('supervisor');
}

/**
 * Cek apakah user adalah client/klien.
 */
function isClient() {
    return hasRole('client');
}

/**
 * Tampilkan badge role dengan warna berbeda
 */
function roleBadge($role) {
    $role = normalizeRole($role);
    $colors = [
        'admin' => '#e74c3c',
        'analis' => '#2ecc71',
        'supervisor' => '#f39c12',
        'client' => '#3498db'
    ];
    $color = $colors[$role] ?? '#95a5a6';
    return "<span style='background:$color;color:white;padding:2px 8px;border-radius:12px;font-size:.65rem;display:inline-block;'>" . strtoupper($role) . "</span>";
}

/**
 * Tampilkan badge read only
 */
function readOnlyBadge() {
    return "<span class='readonly-badge' style='background:#f39c12;color:#1a1a2e;padding:4px 12px;border-radius:20px;font-size:.7rem;font-weight:600;display:inline-flex;align-items:center;gap:6px;'>🔒 Mode Read Only</span>";
}

// ============================================================
// AKSES PER MODUL
// ============================================================

/**
 * Penerimaan Sampel
 * - Admin: full akses (CRUD)
 * - Analis: read only
 * - Supervisor: read only
 */
function canAccessPenerimaan() {
    return isAdmin() || isAnalis() || isSupervisor();
}

function canEditPenerimaan() {
    return isAdmin();
}

function canDeletePenerimaan() {
    return isAdmin();
}

/**
 * Pengujian
 * - Semua role: full akses
 */
function canAccessPengujian() {
    return isAdmin() || isAnalis() || isSupervisor();
}

function canEditPengujian() {
    return isAdmin() || isAnalis() || isSupervisor();
}

/**
 * Preparasi
 * - Admin: read only
 * - Analis: full akses
 * - Supervisor: read only
 */
function canAccessPreparasi() {
    return isAdmin() || isAnalis() || isSupervisor();
}

function canEditPreparasi() {
    return isAnalis();
}

function canViewPreparasi() {
    return isAdmin() || isSupervisor();
}

/**
 * QC
 * - Admin: read only
 * - Analis: full akses
 * - Supervisor: read only
 */
function canAccessQC() {
    return isAdmin() || isAnalis() || isSupervisor();
}

function canEditQC() {
    return isAnalis();
}

function canViewQC() {
    return isAdmin() || isSupervisor();
}

/**
 * Invoice
 * - Admin: full akses
 * - Analis: tidak ada akses
 * - Supervisor: read only
 */
function canAccessInvoice() {
    return isAdmin() || isSupervisor();
}

function canEditInvoice() {
    return isAdmin();
}

function canViewInvoice() {
    return isAdmin() || isSupervisor();
}

/**
 * Bahan (Inventaris)
 * - Semua role: full akses
 */
function canAccessBahan() {
    return isAdmin() || isAnalis() || isSupervisor();
}

function canEditBahan() {
    return isAdmin() || isAnalis() || isSupervisor();
}

/**
 * Peralatan
 * - Semua role: full akses
 */
function canAccessPeralatan() {
    return isAdmin() || isAnalis() || isSupervisor();
}

function canEditPeralatan() {
    return isAdmin() || isAnalis() || isSupervisor();
}

/**
 * Laporan
 * - Admin: full akses
 * - Analis: tidak ada akses
 * - Supervisor: full akses
 */
function canAccessLaporan() {
    return isAdmin() || isSupervisor();
}

/**
 * Work Order
 * - Semua role: full akses
 */
function canAccessWorkOrder() {
    return isAdmin() || isAnalis() || isSupervisor();
}

function canEditWorkOrder() {
    return isAdmin() || isAnalis() || isSupervisor();
}

/**
 * Dashboard - semua role bisa akses
 */
function canAccessDashboard() {
    return true;
}

/**
 * Monitoring client
 */
function canAccessClientMonitoring() {
    return isClient();
}

/**
 * Manajemen Pengguna - hanya admin
 */
function canAccessPengguna() {
    return isAdmin();
}

// ============================================================
// END OF ROLE HELPER FUNCTIONS
// ============================================================

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function clientAccessTableReady(PDO $pdo): bool {
    return tableExists($pdo, 'client_access');
}

function penggunaRoleValues(PDO $pdo): array {
    $stmt = $pdo->query("SHOW COLUMNS FROM pengguna LIKE 'role'");
    $col = $stmt->fetch();
    if (!$col || empty($col['Type'])) return [];

    preg_match_all("/'([^']+)'/", $col['Type'], $matches);
    return $matches[1] ?? [];
}

function preferredClientRole(PDO $pdo): string {
    $roles = penggunaRoleValues($pdo);
    if (in_array('client', $roles, true)) return 'client';
    if (in_array('klien', $roles, true)) return 'klien';
    return 'client';
}

function generateClientPassword(int $length = 10): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}

function generateClientUsername(PDO $pdo, string $code): string {
    $base = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $code));
    $base = $base ?: (string)time();
    $base = 'client_' . substr($base, 0, 34);
    $username = $base;
    $i = 1;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pengguna WHERE username = ?");
    while (true) {
        $stmt->execute([$username]);
        if ((int)$stmt->fetchColumn() === 0) return $username;
        $username = substr($base, 0, 38) . $i;
        $i++;
    }
}

function createClientAccountForAccess(PDO $pdo, array $data): array {
    if (!clientAccessTableReady($pdo)) {
        return [
            'created' => false,
            'message' => 'Tabel client_access belum tersedia. Jalankan scripts/sql/patch_client_role.sql.',
        ];
    }

    $code = trim($data['kode_akses'] ?? $data['nomor'] ?? '');
    if ($code === '') $code = 'CLIENT-' . date('ymdHis');

    try {
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) $pdo->beginTransaction();

        $username = generateClientUsername($pdo, $code);
        $password = generateClientPassword();
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $role = preferredClientRole($pdo);
        $klien = trim($data['klien'] ?? 'Client');
        $email = trim($data['email'] ?? '');

        $stmtUser = $pdo->prepare(
            "INSERT INTO pengguna (nama, username, password, email, role, status)
             VALUES (?, ?, ?, ?, ?, 'aktif')"
        );
        $stmtUser->execute([$klien . ' - ' . $code, $username, $hash, $email, $role]);
        $penggunaId = (int)$pdo->lastInsertId();

        $stmtAccess = $pdo->prepare(
            "INSERT INTO client_access
             (pengguna_id, submission_id, penerimaan_id, kode_akses, klien, email, status)
             VALUES (?, ?, ?, ?, ?, ?, 'aktif')"
        );
        $stmtAccess->execute([
            $penggunaId,
            !empty($data['submission_id']) ? (int)$data['submission_id'] : null,
            !empty($data['penerimaan_id']) ? (int)$data['penerimaan_id'] : null,
            $code,
            $klien,
            $email,
        ]);

        if ($ownTransaction) $pdo->commit();

        return [
            'created' => true,
            'user_id' => $penggunaId,
            'username' => $username,
            'password' => $password,
            'kode_akses' => $code,
        ];
    } catch (Exception $e) {
        if (!empty($ownTransaction) && $pdo->inTransaction()) $pdo->rollBack();
        return ['created' => false, 'message' => $e->getMessage()];
    }
}

function attachClientAccessToPenerimaan(PDO $pdo, int $submissionId, int $penerimaanId): int {
    if (!clientAccessTableReady($pdo) || !$submissionId || !$penerimaanId) return 0;

    $stmt = $pdo->prepare(
        "UPDATE client_access
         SET penerimaan_id = ?, status = 'aktif'
         WHERE submission_id = ? AND penerimaan_id IS NULL"
    );
    $stmt->execute([$penerimaanId, $submissionId]);
    return $stmt->rowCount();
}

function clientCanAccessInvoice(PDO $pdo, int $invoiceId, int $penggunaId): bool {
    if (!clientAccessTableReady($pdo)) return false;

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM invoice i
         JOIN client_access ca ON ca.penerimaan_id = i.penerimaan_id
         WHERE i.id = ? AND ca.pengguna_id = ?"
    );
    $stmt->execute([$invoiceId, $penggunaId]);
    return (int)$stmt->fetchColumn() > 0;
}

function syncPenerimaanCompletion(PDO $pdo, ?int $penerimaanId): bool {
    if (!$penerimaanId) return false;

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) AS selesai
         FROM sampel
         WHERE penerimaan_id = ?"
    );
    $stmt->execute([$penerimaanId]);
    $row = $stmt->fetch();
    $total = (int)($row['total'] ?? 0);
    $selesai = (int)($row['selesai'] ?? 0);

    if ($total > 0 && $total === $selesai) {
        $pdo->prepare("UPDATE penerimaan_sampel SET status='selesai' WHERE id=?")
            ->execute([$penerimaanId]);
        return true;
    }

    return false;
}

function cleanupCompletedClientAccounts(PDO $pdo, ?int $penerimaanId = null): int {
    if (!clientAccessTableReady($pdo)) return 0;

    $params = [];
    $filter = '';
    if ($penerimaanId) {
        $filter = ' AND ca.penerimaan_id = ?';
        $params[] = $penerimaanId;
    }

    $sql = "
        SELECT DISTINCT u.id
        FROM pengguna u
        JOIN client_access ca ON ca.pengguna_id = u.id
        WHERE u.role IN ('client','klien')
          $filter
          AND EXISTS (
              SELECT 1
              FROM client_access ca_done
              JOIN penerimaan_sampel p_done ON p_done.id = ca_done.penerimaan_id
              WHERE ca_done.pengguna_id = u.id
                AND p_done.status = 'selesai'
                AND NOT EXISTS (
                    SELECT 1 FROM sampel s_done
                    WHERE s_done.penerimaan_id = p_done.id
                      AND s_done.status <> 'selesai'
                )
                AND EXISTS (
                    SELECT 1 FROM invoice i_done
                    WHERE i_done.penerimaan_id = p_done.id
                      AND i_done.status = 'lunas'
                )
          )
          AND NOT EXISTS (
              SELECT 1
              FROM client_access ca_open
              LEFT JOIN penerimaan_sampel p_open ON p_open.id = ca_open.penerimaan_id
              WHERE ca_open.pengguna_id = u.id
                AND (
                    ca_open.penerimaan_id IS NULL
                    OR p_open.status <> 'selesai'
                    OR EXISTS (
                        SELECT 1 FROM sampel s_open
                        WHERE s_open.penerimaan_id = p_open.id
                          AND s_open.status <> 'selesai'
                    )
                    OR NOT EXISTS (
                        SELECT 1 FROM invoice i_open
                        WHERE i_open.penerimaan_id = p_open.id
                          AND i_open.status = 'lunas'
                    )
                )
          )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $userIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!$userIds) return 0;

    $deleted = 0;
    try {
        $pdo->beginTransaction();
        $stLog = $pdo->prepare("UPDATE log_aktivitas SET pengguna_id = NULL WHERE pengguna_id = ?");
        $stAccess = $pdo->prepare("DELETE FROM client_access WHERE pengguna_id = ?");
        $stUser = $pdo->prepare("DELETE FROM pengguna WHERE id = ? AND role IN ('client','klien')");

        foreach ($userIds as $userId) {
            $stLog->execute([$userId]);
            $stAccess->execute([$userId]);
            $stUser->execute([$userId]);
            $deleted += $stUser->rowCount();
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('cleanupCompletedClientAccounts failed: ' . $e->getMessage());
    }

    return $deleted;
}

// ── STATUS BAHAN — SATU SUMBER KEBENARAN ─────────────────────
function statusBahan($stok, $min) {
    $stok = (float)$stok;
    $min  = (float)$min;
    if ($min <= 0) return 'aman';
    if ($stok <= $min * 0.5) return 'kritis';
    if ($stok <= $min)       return 'rendah';
    return 'aman';
}

// ── HITUNG ALERT — SATU SUMBER KEBENARAN ─────────────────────
function hitungAlert(PDO $pdo): array {
    $bahanKritis = (int)$pdo->query(
        "SELECT COUNT(*) FROM bahan WHERE stok_minimum > 0 AND stok <= stok_minimum * 0.5"
    )->fetchColumn();

    $bahanRendah = (int)$pdo->query(
        "SELECT COUNT(*) FROM bahan WHERE stok_minimum > 0 AND stok <= stok_minimum"
    )->fetchColumn();

    $alatMasalah = (int)$pdo->query(
        "SELECT COUNT(*) FROM peralatan WHERE status IN('maintenance','rusak')"
    )->fetchColumn();

    $kalibrasiAlert = (int)$pdo->query(
        "SELECT COUNT(*) FROM peralatan
         WHERE masa_berlaku_kalibrasi IS NOT NULL
           AND masa_berlaku_kalibrasi <= DATE_ADD(NOW(), INTERVAL 7 DAY)"
    )->fetchColumn();

    return [
        'bahan_kritis'   => $bahanKritis,
        'bahan_rendah'   => $bahanRendah,
        'alat_masalah'   => $alatMasalah,
        'kalibrasi'      => $kalibrasiAlert,
        'total'          => $bahanRendah + $alatMasalah + $kalibrasiAlert,
    ];
}

// ── FORMAT TANGGAL ───────────────────────────────────────────
function fmtTgl($dateStr, $format = 'd/m/Y') {
    if (!$dateStr) return '&mdash;';
    return date($format, strtotime($dateStr));
}

function fmtTglPanjang($dateStr) {
    if (!$dateStr) return '&mdash;';
    return date('d F Y', strtotime($dateStr));
}

// ── BADGE STATUS ─────────────────────────────────────────────
function badgeStatus($s) {
    $map = [
        'antrian'     => ['st-blue', 'Antrian'],
        'diuji'       => ['st-ok',   'Diuji'],
        'review'      => ['st-warn', 'Review'],
        'selesai'     => ['st-ok',   'Selesai'],
        'ditolak'     => ['st-err',  'Ditolak'],
        'tersedia'    => ['st-ok',   'Tersedia'],
        'digunakan'   => ['st-blue', 'Digunakan'],
        'maintenance' => ['st-warn', 'Maintenance'],
        'rusak'       => ['st-err',  'Rusak'],
        'aman'        => ['st-ok',   'Aman'],
        'rendah'      => ['st-warn', 'Rendah'],
        'kritis'      => ['st-err',  'Kritis'],
        'lulus'       => ['st-ok',   '&#10003; Lulus'],
        'tidak_lulus' => ['st-err',  '&#10007; Tidak Lulus'],
        'pending'     => ['st-warn', 'Pending'],
        'aktif'       => ['st-ok',   'Aktif'],
        'nonaktif'    => ['st-err',  'Nonaktif'],
        'admin'       => ['st-err',  'Admin'],
        'analis'      => ['st-gold', 'Analis'],
        'supervisor'  => ['st-warn', 'Supervisor'],
        'klien'       => ['st-blue', 'Klien'],
        'client'      => ['st-blue', 'Client'],
        'diterima'    => ['st-blue', 'Diterima'],
        'diproses'    => ['st-warn', 'Diproses'],
        'draft'       => ['st-warn', 'Draft'],
        'diterbitkan' => ['st-blue', 'Diterbitkan'],
        'lunas'       => ['st-ok',   'Lunas'],
        'dibatalkan'  => ['st-err',  'Dibatalkan'],
    ];
    $d = $map[$s] ?? ['st-warn', ucfirst($s)];
    return "<span class='st {$d[0]}'>{$d[1]}</span>";
}

// ── NOMOR SERTIFIKAT DETERMINISTIK ───────────────────────────
function generateCertNum($noRec = null, $klien = '', $tgl = '') {
    if ($noRec) return $noRec;
    $seed = $klien . $tgl;
    return 'LAB-' . date('Ym') . '-' . strtoupper(substr(md5($seed), 0, 4));
}
