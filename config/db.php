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

// Turunkan base URL dari folder project agar asset tetap terbaca meski nama folder berubah.
$projectRoot = realpath(dirname(__DIR__));
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$baseUrl = '/labmineral-main';

if ($projectRoot && $documentRoot) {
    $projectRoot = str_replace('\\', '/', $projectRoot);
    $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');

    if (strpos($projectRoot, $documentRoot) === 0) {
        $relativePath = trim(substr($projectRoot, strlen($documentRoot)), '/');
        $baseUrl = $relativePath === '' ? '' : '/' . $relativePath;
    }
}

define('BASE_URL', $baseUrl);

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
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: '.BASE_URL.'/index.php'); 
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

/**
 * Cek apakah user memiliki role tertentu
 * Mendukung kedua format session: user_role dan role
 */
function hasRole($role) {
    // Cek kedua format session
    $userRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
    
    if (!$userRole) return false;
    
    if (is_array($role)) {
        return in_array($userRole, $role);
    }
    return $userRole === $role;
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
 * Tampilkan badge role dengan warna berbeda
 */
function roleBadge($role) {
    $colors = [
        'admin' => '#e74c3c',
        'analis' => '#2ecc71',
        'supervisor' => '#f39c12',
        'klien' => '#3498db'
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
 * Manajemen Pengguna - hanya admin
 */
function canAccessPengguna() {
    return isAdmin();
}

// ============================================================
// END OF ROLE HELPER FUNCTIONS
// ============================================================

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
