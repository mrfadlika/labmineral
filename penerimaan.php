<?php
// ============================================================
//  penerimaan.php — Penerimaan Sampel + Sample Submission Form
//  UPDATE: Dapat mengambil data dari submission yang diterima
// ============================================================
session_start();
require_once __DIR__ . '/config/db.php';
cekLogin();

// Hanya admin yang boleh mengakses
if (!isAdmin()) {
    $_SESSION['msg'] = 'ERROR: Hanya Administrator yang dapat mengakses halaman Penerimaan Sampel.';
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$pageTitle = 'Penerimaan Sampel';

$msg = $_SESSION['msg'] ?? ''; unset($_SESSION['msg']);
$tab = $_GET['tab'] ?? 'daftar';
$submissionTablesReady = tableExists($pdo, 'submission_sampel') && tableExists($pdo, 'submission_sampel_detail');

// ── Proses konfirmasi penerimaan dari submission ─────────────
$processSubmission = (int)($_GET['process_submission'] ?? 0);
if ($processSubmission) {
    if (!$submissionTablesReady) {
        $_SESSION['msg'] = 'ERROR: Tabel submission belum tersedia. Jalankan scripts/sql/patch_client_role.sql terlebih dahulu.';
        header('Location: ' . BASE_URL . '/penerimaan.php');
        exit;
    }

    // Ambil data submission (tanpa status filter untuk memastikan)
    $subStmt = $pdo->prepare("SELECT * FROM submission_sampel WHERE id = ?");
    $subStmt->execute([$processSubmission]);
    $submission = $subStmt->fetch();
    
    if ($submission) {
        // Jika status masih pending, ubah ke diterima dulu
        if ($submission['status'] === 'pending') {
            $pdo->prepare("UPDATE submission_sampel SET status = 'diterima' WHERE id = ?")->execute([$processSubmission]);
            $submission['status'] = 'diterima';
        }
        
        if ($submission['status'] === 'diterima') {
            // Ambil detail sampel
            $detailStmt = $pdo->prepare("SELECT * FROM submission_sampel_detail WHERE submission_id = ?");
            $detailStmt->execute([$processSubmission]);
            $sampelDetails = $detailStmt->fetchAll();
            
            if (!empty($sampelDetails)) {
                // Generate nomor penerimaan
                $lastRec = $pdo->query("SELECT nomor_penerimaan FROM penerimaan_sampel ORDER BY id DESC LIMIT 1")->fetchColumn();
                $nextNum = $lastRec ? (intval(substr($lastRec, -3)) + 1) : 1;
                $noPenerimaan = 'REC-' . date('ym') . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                
                // Kumpulkan jenis material dan metode unik
                $materials = array_unique(array_column($sampelDetails, 'jenis_material'));
                $metodes = array_unique(array_column($sampelDetails, 'metode_uji'));
                $materialStr = implode(', ', array_filter($materials));
                $metodeStr = implode(', ', array_filter($metodes));
                
                // Buat keterangan dari instruksi submission
                $keterangan = 'Dari submission: ' . $submission['nomor_submission'];
                if (!empty($submission['instruksi_khusus'])) {
                    $keterangan .= ' - Instruksi: ' . $submission['instruksi_khusus'];
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    // Insert ke penerimaan_sampel
                    $stmtRec = $pdo->prepare("
                        INSERT INTO penerimaan_sampel 
                        (nomor_penerimaan, klien, tanggal_terima, jumlah_sampel, jenis_material, metode_uji, keterangan, status, dibuat_oleh)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'diterima', ?)
                    ");
                    $stmtRec->execute([
                        $noPenerimaan,
                        $submission['klien'],
                        date('Y-m-d'),
                        count($sampelDetails),
                        $materialStr,
                        $metodeStr,
                        $keterangan,
                        $_SESSION['user_id']
                    ]);
                    $penerimaanId = $pdo->lastInsertId();
                    
                    // Generate kode sampel
                    $lastKode = $pdo->query("SELECT kode_sampel FROM sampel ORDER BY id DESC LIMIT 1")->fetchColumn();
                    $nextKodeNum = $lastKode ? (intval(substr($lastKode, -3)) + 1) : 1;
                    
                    // Insert sampel
                    $stmtSampel = $pdo->prepare("
                        INSERT INTO sampel 
                        (penerimaan_id, kode_sampel, tanggal_masuk, jenis_material, berat_gram, klien, metode_uji, keterangan, dibuat_oleh)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    foreach ($sampelDetails as $detail) {
                        $kodeSampel = 'S-' . date('ym') . '-' . str_pad($nextKodeNum, 3, '0', STR_PAD_LEFT);
                        $sampelKeterangan = $detail['keterangan'] ?? '';
                        if (!empty($submission['nomor_submission'])) {
                            $sampelKeterangan .= ' (dari submission: ' . $submission['nomor_submission'] . ')';
                        }
                        
                        $stmtSampel->execute([
                            $penerimaanId,
                            $kodeSampel,
                            date('Y-m-d'),
                            $detail['jenis_material'],
                            $detail['berat_gram'],
                            $submission['klien'],
                            $detail['metode_uji'],
                            trim($sampelKeterangan),
                            $_SESSION['user_id']
                        ]);
                        $nextKodeNum++;
                    }
                    
                    // Update status submission menjadi diproses
                    $pdo->prepare("UPDATE submission_sampel SET status = 'diproses' WHERE id = ?")->execute([$processSubmission]);
                    
                    $pdo->commit();
                    
                    $linkedClients = attachClientAccessToPenerimaan($pdo, $processSubmission, (int)$penerimaanId);
                    $clientMsg = '';
                    if ($linkedClients > 0) {
                        $clientMsg = ' Akun client dari submission sudah ditautkan ke penerimaan ini.';
                    } else {
                        $clientAccount = createClientAccountForAccess($pdo, [
                            'kode_akses' => $submission['nomor_submission'],
                            'submission_id' => $processSubmission,
                            'penerimaan_id' => $penerimaanId,
                            'klien' => $submission['klien'],
                            'email' => $submission['email'] ?? '',
                        ]);
                        if ($clientAccount['created'] ?? false) {
                            $waMsg = "Halo {$submission['klien']}, Akun Monitoring LabMineral Anda sudah aktif.\n\nUsername: {$clientAccount['username']}\nPassword: {$clientAccount['password']}\nLink: " . BASE_URL . "/index.php\n\nTerima kasih.";
                            $waLink = "https://wa.me/" . preg_replace('/[^0-9]/', '', $submission['telepon'] ?? '') . "?text=" . urlencode($waMsg);
                            
                            $clientMsg = " Akun client dibuat: <strong>{$clientAccount['username']}</strong> / <strong>{$clientAccount['password']}</strong>. 
                                         <a href='$waLink' target='_blank' class='btn btn-green btn-sm' style='margin-left:10px;text-decoration:none;display:inline-flex;align-items:center;gap:5px'>
                                            <span style='font-size:1.1rem'>📲</span> Kirim via WA
                                         </a>";
                        } elseif (!empty($clientAccount['message'])) {
                            $clientMsg = " Akun client belum dibuat: {$clientAccount['message']}";
                        }
                    }

                    $_SESSION['msg'] = "✅ Penerimaan $noPenerimaan berhasil dibuat dari submission {$submission['nomor_submission']}. " . count($sampelDetails) . " sampel ditambahkan." . $clientMsg;
                    header('Location: ' . BASE_URL . '/penerimaan.php?tab=daftar');
                    exit;
                    
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $_SESSION['msg'] = 'ERROR: Gagal memproses submission. ' . $e->getMessage();
                    header('Location: ' . BASE_URL . '/penerimaan.php');
                    exit;
                }
            } else {
                $_SESSION['msg'] = 'ERROR: Tidak ada detail sampel dalam submission ini.';
                header('Location: ' . BASE_URL . '/penerimaan.php');
                exit;
            }
        } else {
            $_SESSION['msg'] = 'ERROR: Submission sudah diproses sebelumnya.';
            header('Location: ' . BASE_URL . '/penerimaan.php');
            exit;
        }
    } else {
        $_SESSION['msg'] = 'ERROR: Submission tidak ditemukan.';
        header('Location: ' . BASE_URL . '/penerimaan.php');
        exit;
    }
}

// ── Ambil data dari submission jika ada (untuk form) ─────────
$fromSubmission = (int)($_GET['from_submission'] ?? 0);
$submissionData = null;
$submissionSampel = [];

if ($fromSubmission) {
    if (!$submissionTablesReady) {
        $msg = 'ERROR: Tabel submission belum tersedia. Jalankan scripts/sql/patch_client_role.sql terlebih dahulu.';
        $fromSubmission = 0;
    } else {
        // Ambil data submission
        $subStmt = $pdo->prepare("SELECT * FROM submission_sampel WHERE id = ? AND status = 'diterima'");
        $subStmt->execute([$fromSubmission]);
        $submissionData = $subStmt->fetch();
        
        if ($submissionData) {
            // Ambil detail sampel
            $detailStmt = $pdo->prepare("SELECT * FROM submission_sampel_detail WHERE submission_id = ?");
            $detailStmt->execute([$fromSubmission]);
            $submissionSampel = $detailStmt->fetchAll();
            
            // Tampilkan pesan info
            $msg = '<strong>📋 Data dari Submission:</strong> ' . bersihkan($submissionData['nomor_submission']) . 
                   ' - ' . bersihkan($submissionData['klien']) . 
                   ' (' . count($submissionSampel) . ' sampel)';
            
            // Auto-set tab ke batch untuk memudahkan input
            $tab = 'batch';
            
            // Pre-fill data untuk form
            $prefillKlien = $submissionData['klien'];
            $prefillKontak = $submissionData['kontak_person'];
            $prefillEmail = $submissionData['email'];
            $prefillTelepon = $submissionData['telepon'];
            $prefillAlamat = $submissionData['alamat'];
            $prefillPoReferensi = $submissionData['po_referensi'];
            $prefillInstruksi = $submissionData['instruksi_khusus'];
            $prefillCatatan = $submissionData['catatan'];
        } else {
            $msg = 'ERROR: Submission tidak ditemukan atau belum diterima.';
            $fromSubmission = 0;
        }
    }
}

// ── Auto-generate nomor penerimaan ───────────────────────────
$lastRec = $pdo->query("SELECT nomor_penerimaan FROM penerimaan_sampel ORDER BY id DESC LIMIT 1")->fetchColumn();
$nextNum = $lastRec ? (intval(substr($lastRec, -3)) + 1) : 1;
$noAuto  = 'REC-' . date('ym') . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

// ── Daftar penerimaan ────────────────────────────────────────
$search  = trim($_GET['q'] ?? '');
$fStatus = $_GET['status'] ?? '';
$sqlRec  = "SELECT p.*, u.nama AS operator,
                   (SELECT COUNT(*) FROM sampel s WHERE s.penerimaan_id = p.id) AS total_sampel,
                   (SELECT COUNT(*) FROM sampel s JOIN hasil_uji h ON h.sampel_id = s.id
                    WHERE s.penerimaan_id = p.id AND h.kesimpulan='lulus') AS total_lulus,
                   (SELECT COUNT(*) FROM sampel s JOIN hasil_uji h ON h.sampel_id = s.id
                    WHERE s.penerimaan_id = p.id AND h.kesimpulan='tidak_lulus') AS total_tlulus
            FROM penerimaan_sampel p
            LEFT JOIN pengguna u ON p.dibuat_oleh = u.id
            WHERE 1=1";
$pRec = [];
if ($search)  { $sqlRec .= " AND (p.nomor_penerimaan LIKE ? OR p.klien LIKE ?)"; $pRec = array_merge($pRec, ["%$search%","%$search%"]); }
if ($fStatus) { $sqlRec .= " AND p.status = ?"; $pRec[] = $fStatus; }
$sqlRec .= " ORDER BY p.created_at DESC";
$stRec   = $pdo->prepare($sqlRec); $stRec->execute($pRec);
$recList = $stRec->fetchAll();

$materialOpts = ['Bijih Emas','Nikel Laterit','Tembaga','Bauksit','Bijih Besi','Timbal/Seng','Mangan','Kromit','Lainnya'];
$metodeOpts   = ['AAS','XRF','ICP-OES','Gravimetri','Fire Assay','Volumetri'];

// Ambil daftar submission yang sudah diterima untuk dropdown
$submissionsList = [];
if ($submissionTablesReady) {
    $submissionsList = $pdo->query("
        SELECT id, nomor_submission, klien, email, telepon, tanggal_submit,
               (SELECT COUNT(*) FROM submission_sampel_detail WHERE submission_id = submission_sampel.id) AS jumlah_sampel
        FROM submission_sampel
        WHERE status = 'diterima'
        ORDER BY tanggal_submit DESC
    ")->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.tabs{display:flex;gap:4px;margin-bottom:16px;border-bottom:1px solid var(--border);}
.tab-btn{padding:8px 18px;background:none;border:none;border-bottom:3px solid transparent;color:var(--text3);font-size:.82rem;cursor:pointer;font-weight:600;transition:.2s;margin-bottom:-1px;}
.tab-btn:hover{color:var(--text2);}
.tab-btn.active{color:var(--gold);border-bottom-color:var(--gold);}
.tab-pane{display:none;}.tab-pane.active{display:block;}

/* SSF = Sample Submission Form styles */
.ssf-preview{background:#fff;color:#222;border-radius:8px;padding:32px 36px;font-family:Arial,sans-serif;font-size:10pt;max-width:860px;margin:0 auto;}
.ssf-kop{display:flex;align-items:center;border-bottom:3px solid #1e4028;padding-bottom:12px;margin-bottom:6px;}
.ssf-logo{width:64px;height:64px;background:#1e4028;border-radius:6px;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#f0c040;font-weight:900;font-size:9pt;text-align:center;flex-shrink:0;margin-right:14px;line-height:1.3;}
.ssf-kop h1{font-size:14pt;font-weight:900;color:#1e4028;}
.ssf-kop p{font-size:8pt;color:#444;margin-top:2px;line-height:1.5;}
.ssf-divider{height:3px;background:linear-gradient(to right,#1e4028,#f0c040,#1e4028);margin:4px 0 16px;}
.ssf-title{text-align:center;margin-bottom:16px;}
.ssf-title h2{font-size:13pt;font-weight:900;text-decoration:underline;letter-spacing:1px;}
.ssf-title p{font-size:9pt;font-style:italic;color:#555;}
.ssf-section{margin-bottom:14px;}
.ssf-section-title{font-size:9pt;font-weight:700;background:#1e4028;color:#f0c040;padding:4px 10px;border-radius:3px;margin-bottom:8px;display:inline-block;}
.ssf-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;}
.ssf-field{display:flex;flex-direction:column;margin-bottom:6px;}
.ssf-field label{font-size:8pt;font-weight:700;color:#333;margin-bottom:2px;}
.ssf-field .ssf-input{border:none;border-bottom:1px solid #888;padding:4px 2px;font-size:9pt;background:transparent;width:100%;outline:none;color:#222;}
.ssf-field .ssf-input:focus{border-bottom-color:#1e4028;}
.ssf-field .ssf-select{border:none;border-bottom:1px solid #888;padding:4px 2px;font-size:9pt;background:transparent;width:100%;outline:none;color:#222;}
.ssf-tbl{width:100%;border-collapse:collapse;font-size:8.5pt;margin-bottom:6px;}
.ssf-tbl thead th{background:#1e4028;color:#f0c040;padding:5px 7px;text-align:center;border:1px solid #2e6040;}
.ssf-tbl tbody td{padding:5px 7px;border:1px solid #bbb;vertical-align:middle;}
.ssf-tbl tbody td input{border:none;width:100%;background:transparent;font-size:8.5pt;outline:none;padding:2px;}
.ssf-tbl tbody td select{border:none;width:100%;background:transparent;font-size:8.5pt;outline:none;}
.ssf-disclaimer{font-size:7.5pt;text-align:justify;line-height:1.6;color:#333;margin-top:12px;border-top:1px solid #ccc;padding-top:8px;}
.ssf-sign{display:flex;gap:40px;margin-top:16px;}
.ssf-sign-box .sign-label{font-size:8.5pt;font-weight:700;margin-bottom:40px;}
.ssf-sign-box .sign-line{border-top:1px solid #333;min-width:160px;padding-top:4px;font-size:8pt;color:#555;}
.ssf-footer{margin-top:16px;padding-top:8px;border-top:1px solid #ccc;display:flex;justify-content:space-between;font-size:7pt;color:#888;}

/* Submission info card */
.submission-info-card {
    background: #0d2318;
    border: 1px solid var(--gold);
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
}
.submission-info-card .title {
    color: var(--gold);
    font-weight: 600;
    margin-bottom: 8px;
    font-size: .85rem;
}
.submission-info-card .details {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 8px;
    font-size: .75rem;
}
.submission-info-card .details span {
    color: var(--text3);
}
.submission-info-card .details strong {
    color: var(--text);
}

/* Print */
@media print{
    .print-bar, .tabs, .sel-card, #tab-daftar, #tab-batch, .sidebar-footer,
    #sidebar, #topbar, .no-print { display:none !important; }
    #main, #content { display:block !important; overflow:visible !important; height:auto !important; }
    body { overflow:visible !important; height:auto !important; }
    .ssf-preview { padding:10px; }
    @page { size:A4 portrait; margin:12mm 15mm; }
}
</style>

<div class="sec-title">Penerimaan Sampel</div>

<?php if ($msg): ?>
    <div class="alert-box <?= str_starts_with($msg,'ERROR')?'alert-red':'alert-green' ?>" style="margin-bottom:14px">
        <?= str_starts_with($msg,'ERROR')?'&#9888;':'&#10003;' ?> <?= $msg ?>
    </div>
<?php endif; ?>

<!-- TABS -->
<div class="tabs no-print">
    <button class="tab-btn <?= $tab==='daftar'?'active':'' ?>" onclick="switchTab('daftar',this)">&#128203; Daftar Penerimaan</button>
    <button class="tab-btn <?= $tab==='batch'?'active':'' ?>"  onclick="switchTab('batch',this)">&#10133; Penerimaan Baru</button>
    <button class="tab-btn <?= $tab==='ssf'?'active':'' ?>"    onclick="switchTab('ssf',this)">&#128196; Sample Submission Form</button>
</div>

<!-- ══════════════════════════════════════════════════
     TAB 1 — DAFTAR PENERIMAAN
══════════════════════════════════════════════════ -->
<div id="tab-daftar" class="tab-pane <?= $tab==='daftar'?'active':'' ?>">

    <!-- Filter -->
    <form method="GET" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
        <input type="hidden" name="tab" value="daftar"/>
        <input name="q" value="<?= bersihkan($search) ?>" placeholder="&#128269; Cari no. penerimaan / klien..."
               style="flex:1;background:var(--bg3);border:1px solid var(--border);color:var(--text);
                      padding:7px 12px;border-radius:6px;font-size:.8rem;outline:none"/>
        <select name="status" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:6px;font-size:.8rem;outline:none">
            <option value="">Semua Status</option>
            <?php foreach (['diterima','diproses','selesai'] as $st): ?>
                <option value="<?= $st ?>" <?= $fStatus===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-green btn-sm">Cari</button>
    </form>

    <div class="card">
        <div class="card-title">&#128230; Daftar Penerimaan Sampel <span><?= count($recList) ?> batch</span></div>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No. Penerimaan</th><th>Klien</th><th>Tgl Terima</th>
                    <th>Sampel</th><th>Lulus</th><th>Tdk Lulus</th>
                    <th>Status</th><th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recList as $r): ?>
            <tr>
                <td style="font-weight:700;color:var(--gold)"><?= bersihkan($r['nomor_penerimaan']) ?></td>
                <td><?= bersihkan($r['klien']) ?></td>
                <td><?= date('d/m/Y', strtotime($r['tanggal_terima'])) ?></td>
                <td style="text-align:center"><strong><?= $r['total_sampel'] ?></strong></td>
                <td style="text-align:center;color:var(--green)"><?= $r['total_lulus'] ?></td>
                <td style="text-align:center;color:var(--red)"><?= $r['total_tlulus'] ?></td>
                <td>
                    <form method="POST" action="<?= BASE_URL ?>/actions/simpan_penerimaan.php" style="display:inline">
                        <input type="hidden" name="action" value="update_status"/>
                        <input type="hidden" name="id" value="<?= $r['id'] ?>"/>
                        <select name="status" class="sel-inline" onchange="this.form.submit()" style="width:88px;font-size:.7rem">
                            <?php foreach (['diterima','diproses','selesai'] as $st): ?>
                                <option value="<?= $st ?>" <?= $r['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td style="white-space:nowrap">
                    <!-- Cetak SSF untuk batch ini -->
                    <button onclick="cetakSSF('<?= bersihkan($r['nomor_penerimaan']) ?>','<?= bersihkan(addslashes($r['klien'])) ?>','<?= $r['tanggal_terima'] ?>',<?= $r['total_sampel'] ?>)"
                            class="btn btn-gold btn-sm" style="font-size:.68rem;padding:3px 8px" title="Cetak Sample Submission Form">
                        &#128196; SSF
                    </button>
                    <a href="<?= BASE_URL ?>/exports/export_pdf.php?rec=<?= urlencode($r['nomor_penerimaan']) ?>&cetak=1"
                       target="_blank" class="btn btn-red btn-sm" style="font-size:.68rem;padding:3px 8px" title="Export PDF Laporan">
                        &#128196; PDF
                    </a>
                </td>
            </tr>
            <!-- Detail sampel dalam batch -->
            <?php
            $sbList = $pdo->prepare(
                "SELECT s.kode_sampel, s.jenis_material, s.berat_gram, s.metode_uji, s.status
                 FROM sampel s WHERE s.penerimaan_id = ? ORDER BY s.kode_sampel"
            );
            $sbList->execute([$r['id']]);
            $sbs = $sbList->fetchAll();
            foreach ($sbs as $sb): ?>
            <tr style="background:#0a1a0f">
                <td style="padding-left:28px;color:var(--text3);font-size:.75rem">&#9492; <?= bersihkan($sb['kode_sampel']) ?></td>
                <td colspan="2" style="font-size:.75rem;color:var(--text2)"><?= bersihkan($sb['jenis_material']) ?></td>
                <td style="font-size:.75rem;color:var(--text2)"><?= $sb['berat_gram'] ? number_format($sb['berat_gram'],2).' g' : '—' ?></td>
                <td style="font-size:.75rem;color:var(--text2)"><?= bersihkan($sb['metode_uji']) ?></td>
                <td style="font-size:.75rem;color:var(--text2)"><?= badgeStatus($sb['status']) ?></td>
                <td></td>
            </tr>
            <?php endforeach; ?>
            <?php endforeach; ?>
            <?php if (!$recList): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--text3);padding:20px">Tidak ada data.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    
    <!-- Tampilkan daftar submission yang siap diproses -->
    <?php if (!empty($submissionsList)): ?>
    <div class="card" style="margin-top:16px">
        <div class="card-title">📋 Submission Klien Siap Diproses</div>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No. Submission</th>
                    <th>Klien</th>
                    <th>Email</th>
                    <th>Tgl Submit</th>
                    <th>Sampel</th>
                    <th>Aksi</th>
                 </tr>
            </thead>
            <tbody>
            <?php foreach ($submissionsList as $sub): ?>
            <tr>
                <td style="font-family:monospace"><?= bersihkan($sub['nomor_submission']) ?></td>
                <td><?= bersihkan($sub['klien']) ?></td>
                <td><?= bersihkan($sub['email']) ?></td>
                <td><?= fmtTgl($sub['tanggal_submit']) ?></td>
                <td style="text-align:center"><?= $sub['jumlah_sampel'] ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/penerimaan.php?process_submission=<?= $sub['id'] ?>" 
                       class="btn btn-gold btn-sm" style="font-size:.68rem;padding:3px 8px"
                       onclick="return confirm('Proses submission ini ke penerimaan? Data akan langsung masuk ke database.')">
                        📦 Proses ke Penerimaan
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════
     TAB 2 — PENERIMAAN BARU (BATCH)
══════════════════════════════════════════════════ -->
<div id="tab-batch" class="tab-pane <?= $tab==='batch'?'active':'' ?>">
    
    <?php if ($fromSubmission && $submissionData): ?>
    <!-- Informasi Submission yang diproses -->
    <div class="submission-info-card">
        <div class="title">📋 Data Submission: <?= bersihkan($submissionData['nomor_submission']) ?></div>
        <div class="details">
            <div><span>Klien:</span> <strong><?= bersihkan($submissionData['klien']) ?></strong></div>
            <div><span>Kontak Person:</span> <strong><?= bersihkan($submissionData['kontak_person'] ?? '-') ?></strong></div>
            <div><span>Email:</span> <strong><?= bersihkan($submissionData['email'] ?? '-') ?></strong></div>
            <div><span>Telepon:</span> <strong><?= bersihkan($submissionData['telepon'] ?? '-') ?></strong></div>
            <div><span>Tanggal Submit:</span> <strong><?= fmtTgl($submissionData['tanggal_submit']) ?></strong></div>
            <?php if ($submissionData['po_referensi']): ?>
            <div><span>PO/Referensi:</span> <strong><?= bersihkan($submissionData['po_referensi']) ?></strong></div>
            <?php endif; ?>
        </div>
        <?php if ($submissionData['instruksi_khusus']): ?>
        <div style="margin-top:8px; font-size:.75rem">
            <span style="color:var(--text3)">Instruksi Khusus:</span> 
            <strong><?= nl2br(bersihkan($submissionData['instruksi_khusus'])) ?></strong>
        </div>
        <?php endif; ?>
        <div style="margin-top:8px; font-size:.75rem; color:var(--green)">
            📦 <?= count($submissionSampel) ?> sampel akan diproses
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-title">&#10133; Penerimaan Sampel Baru</div>
        <form method="POST" action="<?= BASE_URL ?>/actions/simpan_penerimaan.php" id="formPenerimaan">
            <input type="hidden" name="from_submission" value="<?= $fromSubmission ?>">
            <div class="form-row">
                <div class="form-group"><label>No. Penerimaan</label><input name="nomor_penerimaan" value="<?= $noAuto ?>" readonly/></div>
                <div class="form-group"><label>Tanggal Terima</label><input type="date" name="tanggal_terima" value="<?= date('Y-m-d') ?>" required/></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Klien / Perusahaan</label>
                    <input name="klien" id="inputKlien" placeholder="Nama perusahaan" required 
                           value="<?= isset($prefillKlien) ? bersihkan($prefillKlien) : '' ?>" 
                           list="klienSuggest"/>
                    <datalist id="klienSuggest">
                        <?php $klienAll = $pdo->query("SELECT DISTINCT klien FROM sampel WHERE klien IS NOT NULL ORDER BY klien")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($klienAll as $k): ?><option value="<?= bersihkan($k) ?>"><?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Keterangan Batch</label>
                    <textarea name="keterangan" rows="1" placeholder="Catatan penerimaan..."><?= isset($prefillCatatan) ? bersihkan($prefillCatatan) : '' ?></textarea>
                </div>
            </div>
            
            <div style="border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:14px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                    <span style="font-size:.8rem;font-weight:600;color:var(--gold)">&#129704; Daftar Sampel dalam Batch</span>
                    <button type="button" class="btn btn-green btn-sm" onclick="tambahBarisBatch()">&#10133; Tambah Sampel</button>
                </div>
                <div id="batchRows"></div>
                <div style="font-size:.72rem;color:var(--text3);margin-top:6px">Total: <strong id="totalBatch" style="color:var(--gold)">0</strong> sampel</div>
            </div>

            <button type="submit" class="btn btn-gold">&#128190; Simpan Penerimaan &amp; Semua Sampel</button>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     TAB 3 — SAMPLE SUBMISSION FORM
══════════════════════════════════════════════════ -->
<div id="tab-ssf" class="tab-pane <?= $tab==='ssf'?'active':'' ?>">

    <!-- Panel konfigurasi SSF -->
    <div class="card no-print" style="margin-bottom:16px">
        <div class="card-title">&#9881; Konfigurasi Sample Submission Form</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
            <div class="form-group">
                <label>Nama Klien (opsional)</label>
                <input id="ssfKlien" placeholder="Kosongkan untuk form kosong" list="klienSuggestSSF"/>
                <datalist id="klienSuggestSSF">
                    <?php foreach ($klienAll as $k): ?><option value="<?= bersihkan($k) ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group">
                <label>Jumlah Baris Sampel</label>
                <select id="ssfJumlah">
                    <?php for ($i=1;$i<=10;$i++): ?><option value="<?= $i ?>" <?= $i==5?'selected':'' ?>><?= $i ?> sampel</option><?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Tanggal Form</label>
                <input type="date" id="ssfTanggal" value="<?= date('Y-m-d') ?>"/>
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:12px">
            <button class="btn btn-green" onclick="generateSSF()">&#128065; Preview Form</button>
            <button class="btn btn-gold" onclick="window.print()">&#128196; Cetak / Simpan PDF</button>
        </div>
    </div>

    <!-- Preview SSF -->
    <div id="ssfPreview"></div>
</div>

<script>
// ── TAB SWITCH ────────────────────────────────────────────────
function switchTab(name, el) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    if (el) el.classList.add('active');
    history.replaceState(null,'','?tab='+name);
}

// ── BATCH FORM ────────────────────────────────────────────────
const matOpts = <?= json_encode($materialOpts) ?>;
const metOpts = <?= json_encode($metodeOpts) ?>;
let batchRowCnt = 0;

function tambahBarisBatch(jenisMaterial = '', beratGram = '', metodeUji = '', keterangan = '') {
    batchRowCnt++;
    const i = batchRowCnt;
    const mO = matOpts.map(m => `<option ${m === jenisMaterial ? 'selected' : ''}>${m}</option>`).join('');
    const eO = metOpts.map(m => `<option ${m === metodeUji ? 'selected' : ''}>${m}</option>`).join('');
    const html = `
    <div id="br${i}" style="display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr auto;gap:8px;margin-bottom:8px;align-items:end">
        <div class="form-group">
            <label style="font-size:.7rem;color:var(--text3)">Material #${i}</label>
            <select name="sampel[${i}][jenis_material]">${mO}</select>
        </div>
        <div class="form-group">
            <label style="font-size:.7rem;color:var(--text3)">Berat (g)</label>
            <input type="number" step="0.001" name="sampel[${i}][berat_gram]" value="${beratGram}" placeholder="0.000"/>
        </div>
        <div class="form-group">
            <label style="font-size:.7rem;color:var(--text3)">Metode</label>
            <select name="sampel[${i}][metode_uji]">${eO}</select>
        </div>
        <div class="form-group">
            <label style="font-size:.7rem;color:var(--text3)">Keterangan</label>
            <input type="text" name="sampel[${i}][keterangan]" value="${keterangan}" placeholder="Opsional"/>
        </div>
        <div><label style="display:block;margin-bottom:5px">&nbsp;</label>
            <button type="button" onclick="hapusBarisBatch(${i})"
                    style="background:var(--red);color:#fff;border:none;border-radius:5px;padding:6px 10px;cursor:pointer;font-size:.75rem">&#10005;</button>
        </div>
    </div>`;
    document.getElementById('batchRows').insertAdjacentHTML('beforeend', html);
    updateBatchCount();
}

function hapusBarisBatch(i) {
    document.getElementById('br' + i)?.remove();
    updateBatchCount();
}

function updateBatchCount() {
    document.getElementById('totalBatch').textContent =
        document.querySelectorAll('#batchRows > div').length;
}

// Inisialisasi: tambah baris batch (dengan data dari submission jika ada)
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($submissionSampel): ?>
    <?php foreach ($submissionSampel as $idx => $s): ?>
    tambahBarisBatch(
        '<?= addslashes($s['jenis_material']) ?>',
        '<?= $s['berat_gram'] ?>',
        '<?= addslashes($s['metode_uji']) ?>',
        '<?= addslashes($s['keterangan']) ?>'
    );
    <?php endforeach; ?>
    <?php else: ?>
    tambahBarisBatch();
    <?php endif; ?>
});

// ── SAMPLE SUBMISSION FORM GENERATOR ────────────────────────────────────────
function generateSSF(noRec, klienPre, tglPre, jmlPre) {
    const klien  = klienPre  || document.getElementById('ssfKlien')?.value   || '';
    const jumlah = jmlPre    || parseInt(document.getElementById('ssfJumlah')?.value || 5);
    const tgl    = tglPre    || document.getElementById('ssfTanggal')?.value  || '';
    const noForm = noRec     || 'SSF-' + Date.now().toString().slice(-6);

    const matSelectOpts = <?= json_encode($materialOpts) ?>.map(m => `<option>${m}</option>`).join('');
    const metSelectOpts = <?= json_encode($metodeOpts) ?>.map(m => `<option>${m}</option>`).join('');

    let rows = '';
    for (let i = 1; i <= jumlah; i++) {
        rows += `<tr>
            <td style="text-align:center;font-weight:700">${i}</td>
            <td><input class="ssf-input-cell" placeholder="Kode sampel" style="width:100%;border:none;background:transparent;font-size:8.5pt;outline:none;padding:2px"/></td>
            <td>
                <select style="width:100%;border:none;background:transparent;font-size:8.5pt;outline:none">
                    <option value="">— Pilih —</option>
                    ${matSelectOpts}
                </select>
            </td>
            <td><input placeholder="gram" style="width:100%;border:none;background:transparent;font-size:8.5pt;outline:none;text-align:center"/></td>
            <td>
                <select style="width:100%;border:none;background:transparent;font-size:8.5pt;outline:none">
                    <option value="">— Pilih —</option>
                    ${metSelectOpts}
                </select>
            </td>
            <td><input placeholder="Keterangan" style="width:100%;border:none;background:transparent;font-size:8.5pt;outline:none"/></td>
         </tr>`;
    }

    const tglFmt = tgl ? new Date(tgl).toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'}) : '___________________';

    const html = `
    <div class="ssf-preview" id="ssfDoc">
        <div class="ssf-kop">
            <div class="ssf-logo">&#9879;<br>LAB<br>MINERAL</div>
            <div>
                <h1>LABMINERAL PRO</h1>
                <p>Laboratorium Uji Mineral &amp; Logam<br>
                Jl. Tamalanrea Raya, Makassar 90245, Sulawesi Selatan<br>
                Telp/Fax. +62 411 000-0000 &nbsp;|&nbsp; Email: lab@labmineral.co.id</p>
            </div>
        </div>
        <div class="ssf-divider"></div>
        <div class="ssf-title">
            <h2>SAMPLE SUBMISSION FORM</h2>
            <p>(Formulir Pengiriman Sampel)</p>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:8.5pt;color:#666;margin-bottom:14px">
            <span>No. Form: <strong style="color:#1e4028">${noForm}</strong></span>
            <span>Tanggal: <strong>${tglFmt}</strong></span>
        </div>
        <div class="ssf-section">
            <div class="ssf-section-title">A. Informasi Klien / Customer Information</div>
            <div class="ssf-grid">
                <div class="ssf-field"><label>Nama Perusahaan / Company Name</label><input class="ssf-input" value="${klien}" placeholder="________________________________"/></div>
                <div class="ssf-field"><label>Nama Kontak / Contact Person</label><input class="ssf-input" placeholder="________________________________"/></div>
                <div class="ssf-field"><label>Alamat / Address</label><input class="ssf-input" placeholder="________________________________"/></div>
                <div class="ssf-field"><label>No. Telepon / Phone</label><input class="ssf-input" placeholder="________________________________"/></div>
                <div class="ssf-field"><label>Email</label><input class="ssf-input" placeholder="________________________________"/></div>
                <div class="ssf-field"><label>No. PO / Referensi</label><input class="ssf-input" placeholder="________________________________"/></div>
            </div>
        </div>
        <div class="ssf-section">
            <div class="ssf-section-title">B. Detail Pengiriman / Submission Details</div>
            <div class="ssf-grid">
                <div class="ssf-field"><label>Tanggal Pengiriman / Date of Submission</label><input class="ssf-input" type="date" value="${tgl}"/></div>
                <div class="ssf-field"><label>Bentuk Sampel / Form of Sample</label><select class="ssf-select"><option>Pulp</option><option>Rock</option><option>Soil</option><option>Core</option><option>Sludge</option><option>Lainnya</option></select></div>
                <div class="ssf-field"><label>Jumlah Sampel / Number of Samples</label><input class="ssf-input" value="${jumlah}" type="number"/></div>
                <div class="ssf-field"><label>Instruksi Khusus / Special Instructions</label><input class="ssf-input" placeholder="________________________________"/></div>
            </div>
        </div>
        <div class="ssf-section">
            <div class="ssf-section-title">C. Daftar Sampel / List of Samples</div>
            <table class="ssf-tbl">
                <thead><tr><th width="30">No</th><th>Kode Sampel / Sample ID</th><th>Material</th><th>Berat (g)</th><th>Metode</th><th>Keterangan</th></tr></thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
        <div class="ssf-disclaimer">
            <strong>PERNYATAAN / DECLARATION:</strong> Saya yang bertanda tangan di bawah ini menyatakan bahwa sampel yang diserahkan adalah benar.
        </div>
        <div class="ssf-sign">
            <div class="ssf-sign-box">
                <div class="sign-label">Diserahkan oleh / Submitted by</div>
                <div class="sign-line">Nama: ___________________</div>
            </div>
            <div class="ssf-sign-box" style="margin-left:auto">
                <div class="sign-label">Diterima oleh / Received by</div>
                <div class="sign-line">Nama: ___________________</div>
            </div>
        </div>
    </div>`;
    document.getElementById('ssfPreview').innerHTML = html;
}

function cetakSSF(noRec, klien, tgl, jml) {
    switchTab('ssf');
    generateSSF(noRec, klien, tgl, jml);
    setTimeout(() => window.print(), 500);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
