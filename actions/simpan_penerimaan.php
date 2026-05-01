<?php
// ============================================================
//  actions/simpan_penerimaan.php
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
cekLogin();

if (!isAdmin()) {
    $_SESSION['msg'] = 'ERROR: Hanya Administrator yang dapat mengubah penerimaan sampel.';
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$action = $_POST['action'] ?? 'tambah';

// ── Update status batch ──────────────────────────────────────
if ($action === 'update_status') {
    $pdo->prepare("UPDATE penerimaan_sampel SET status = ? WHERE id = ?")
        ->execute([$_POST['status'], (int)$_POST['id']]);
    
    $deleted = cleanupCompletedClientAccounts($pdo, (int)$_POST['id']);
    $_SESSION['msg'] = 'Status penerimaan diperbarui.';
    if ($deleted > 0) {
        $_SESSION['msg'] .= " $deleted akun client selesai dan otomatis dihapus.";
    }
    
    header('Location: ' . BASE_URL . '/pages/penerimaan.php');
    exit;
}

// ── Konfirmasi admin ─────────────────────────────────────────
if ($action === 'konfirmasi') {
    $pdo->prepare("UPDATE penerimaan_sampel SET is_confirmed = 1 WHERE id = ?")
        ->execute([(int)$_POST['id']]);
    $_SESSION['msg'] = 'Batch penerimaan telah dikonfirmasi oleh Admin.';
    header('Location: ' . BASE_URL . '/pages/penerimaan.php');
    exit;
}

// ── Simpan penerimaan baru + semua sampel ────────────────────
$noPenerimaan = trim($_POST['nomor_penerimaan'] ?? '');
$klien        = trim($_POST['klien'] ?? '');
$tglTerima    = $_POST['tanggal_terima'] ?? date('Y-m-d');
$keterangan   = trim($_POST['keterangan'] ?? '');
$sampelInput  = $_POST['sampel'] ?? [];

// Validasi minimal
if (!$noPenerimaan || !$klien) {
    $_SESSION['msg'] = 'ERROR: Nomor penerimaan dan klien wajib diisi.';
    header('Location: ' . BASE_URL . '/pages/penerimaan.php');
    exit;
}

if (empty($sampelInput)) {
    $_SESSION['msg'] = 'ERROR: Minimal 1 sampel harus ditambahkan.';
    header('Location: ' . BASE_URL . '/pages/penerimaan.php');
    exit;
}

// Cek duplikat nomor penerimaan
$cek = $pdo->prepare("SELECT id FROM penerimaan_sampel WHERE nomor_penerimaan = ?");
$cek->execute([$noPenerimaan]);
if ($cek->fetch()) {
    $_SESSION['msg'] = "ERROR: Nomor penerimaan $noPenerimaan sudah ada.";
    header('Location: ' . BASE_URL . '/pages/penerimaan.php');
    exit;
}

// Kumpulkan info material & metode untuk ringkasan batch
$materialsArr = [];
$metodeArr    = [];
foreach ($sampelInput as $s) {
    if (!empty($s['jenis_material'])) $materialsArr[] = $s['jenis_material'];
    if (!empty($s['metode_uji']))     $metodeArr[]    = $s['metode_uji'];
}
$materialStr = implode(', ', array_unique($materialsArr));
$metodeStr   = implode(', ', array_unique($metodeArr));

// Mulai transaksi
try {
    $pdo->beginTransaction();

    // 1. Insert batch penerimaan
    $stmtRec = $pdo->prepare(
        "INSERT INTO penerimaan_sampel
         (nomor_penerimaan, klien, tanggal_terima, jumlah_sampel, jenis_material, metode_uji, keterangan, status, dibuat_oleh)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'diterima', ?)"
    );
    $stmtRec->execute([
        $noPenerimaan,
        $klien,
        $tglTerima,
        count($sampelInput),
        $materialStr,
        $metodeStr,
        $keterangan,
        $_SESSION['user_id'],
    ]);
    $penerimaanId = $pdo->lastInsertId();

    // 2. Auto-generate kode sampel berikutnya
    $lastKode = $pdo->query("SELECT kode_sampel FROM sampel ORDER BY id DESC LIMIT 1")->fetchColumn();
    $nextNum  = $lastKode ? (intval(substr($lastKode, -3)) + 1) : 1;

    // 3. Insert setiap sampel
    $stmtSampel = $pdo->prepare(
        "INSERT INTO sampel
         (penerimaan_id, kode_sampel, tanggal_masuk, jenis_material, berat_gram, klien, metode_uji, keterangan, dibuat_oleh)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $kodeList = [];
    foreach ($sampelInput as $s) {
        $kodeSampel = 'S-' . date('ym') . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
        $stmtSampel->execute([
            $penerimaanId,
            $kodeSampel,
            $tglTerima,
            trim($s['jenis_material'] ?? ''),
            !empty($s['berat_gram']) ? (float)$s['berat_gram'] : null,
            $klien,
            trim($s['metode_uji'] ?? ''),
            trim($s['keterangan'] ?? ''),
            $_SESSION['user_id'],
        ]);
        $kodeList[] = $kodeSampel;
        $nextNum++;
    }

    $pdo->commit();

    $jumlah = count($kodeList);
    $kodeStr = implode(', ', $kodeList);
    $_SESSION['msg'] = "Penerimaan $noPenerimaan berhasil disimpan. $jumlah sampel dibuat: $kodeStr.";

    $clientAccount = createClientAccountForAccess($pdo, [
        'kode_akses' => $noPenerimaan,
        'penerimaan_id' => $penerimaanId,
        'klien' => $klien,
    ]);

    if ($clientAccount['created'] ?? false) {
        $_SESSION['msg'] .= " Akun client: username {$clientAccount['username']}, password {$clientAccount['password']}.";
    } elseif (!empty($clientAccount['message'])) {
        $_SESSION['msg'] .= " Akun client belum dibuat: {$clientAccount['message']}";
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['msg'] = 'ERROR: Gagal menyimpan — ' . $e->getMessage();
}

header('Location: ' . BASE_URL . '/pages/penerimaan.php');
exit;
