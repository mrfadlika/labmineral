<?php
/* ============================================================
   actions/simpan_qc.php — P2
   ============================================================ */
session_start();
require_once __DIR__ . '/../config/db.php';
cekLogin();

$action = $_POST['action'] ?? 'input';

// ── Input data QC baru ────────────────────────────────────────
if ($action === 'input' || !$action) {
    $prepId   = !empty($_POST['preparasi_id']) ? (int)$_POST['preparasi_id'] : null;
    $sampelId = null;

    if ($prepId) {
        $row = $pdo->prepare("SELECT sampel_id FROM preparasi_sampel WHERE id=?");
        $row->execute([$prepId]); $r = $row->fetch();
        $sampelId = $r ? $r['sampel_id'] : null;
    }

    if (!$sampelId) {
        $_SESSION['msg'] = 'ERROR: Data preparasi tidak valid.';
        header('Location: '.BASE_URL.'/pages/qc.php'); exit;
    }

    $nilaiQc   = (float)($_POST['nilai_qc']       ?? 0);
    $nilaiExp  = !empty($_POST['nilai_expected']) ? (float)$_POST['nilai_expected'] : null;
    $bMinPct   = (float)($_POST['batas_min_pct']  ?? 85);
    $bMaksPct  = (float)($_POST['batas_maks_pct'] ?? 115);

    // Recovery & flag dihitung oleh trigger, tapi hitung di PHP untuk verifikasi
    $recPct = ($nilaiExp && $nilaiExp > 0) ? ($nilaiQc / $nilaiExp) * 100 : null;
    $flag   = 'pass';
    if ($recPct !== null) {
        if ($recPct < $bMinPct || $recPct > $bMaksPct) $flag = 'fail';
        elseif ($recPct < $bMinPct + 5 || $recPct > $bMaksPct - 5) $flag = 'warning';
    }

    $pdo->prepare(
        "INSERT INTO qc_sampel
         (preparasi_id, sampel_id, tipe_qc, parameter, nilai_qc,
          nilai_expected, satuan, batas_min_pct, batas_maks_pct,
          flag, tanggal_uji)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $prepId, $sampelId,
        $_POST['tipe_qc'],
        trim($_POST['parameter']      ?? ''),
        $nilaiQc, $nilaiExp,
        $_POST['satuan']              ?? 'g/t',
        $bMinPct, $bMaksPct,
        $flag,
        $_POST['tanggal_uji'],
    ]);

    $flagMsg = strtoupper($flag);
    $_SESSION['msg'] = "Data QC berhasil disimpan. Flag: $flagMsg"
        . ($recPct !== null ? " | Recovery: ".number_format($recPct,1)."%" : "");
    header('Location: '.BASE_URL.'/pages/qc.php?tab=dashboard'); exit;
}

// ── Review QC oleh supervisor ────────────────────────────────
if ($action === 'review') {
    $qcId      = (int)$_POST['qc_id'];
    $keputusan = $_POST['keputusan'] ?? 'disetujui'; // disetujui | ditolak
    $reviewer  = !empty($_POST['reviewer_id']) ? (int)$_POST['reviewer_id'] : $_SESSION['user_id'];
    $catatan   = trim($_POST['catatan_review'] ?? '');

    $pdo->prepare(
        "UPDATE qc_sampel SET status_qc=?, reviewer_id=?, catatan_review=? WHERE id=?"
    )->execute([$keputusan, $reviewer, $catatan, $qcId]);

    $_SESSION['msg'] = 'QC berhasil di-review: '.strtoupper($keputusan).'.';
    header('Location: '.BASE_URL.'/pages/qc.php?tab=review'); exit;
}

header('Location: '.BASE_URL.'/pages/qc.php'); exit;
