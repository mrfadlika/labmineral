<?php
// ============================================================
//  client_monitoring.php — Portal monitoring sampel untuk client
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
cekLogin();

if (!isClient()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$pageTitle = 'Monitoring Sampel';
$msg = $_SESSION['msg'] ?? ''; unset($_SESSION['msg']);
$userId = (int)($_SESSION['user_id'] ?? 0);
$accessList = [];

if (clientAccessTableReady($pdo)) {
    $stmt = $pdo->prepare("
        SELECT ca.*,
               sub.nomor_submission, sub.status AS submission_status,
               sub.tanggal_submit, sub.kontak_person, sub.po_referensi,
               p.nomor_penerimaan, p.tanggal_terima, p.status AS penerimaan_status,
               p.jumlah_sampel, p.jenis_material, p.metode_uji, p.keterangan AS penerimaan_keterangan
        FROM client_access ca
        LEFT JOIN submission_sampel sub ON sub.id = ca.submission_id
        LEFT JOIN penerimaan_sampel p ON p.id = ca.penerimaan_id
        WHERE ca.pengguna_id = ?
        ORDER BY ca.created_at DESC
    ");
    $stmt->execute([$userId]);
    $accessList = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.mon-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px}
.mon-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;flex-wrap:wrap}
.mon-title{font-size:1.1rem;font-weight:700;color:var(--gold)}
.mon-meta{font-size:.85rem;color:var(--text3)}
.stepper{display:flex;justify-content:space-between;position:relative;margin-top:20px;padding-bottom:10px}
.stepper::before{content:"";position:absolute;top:15px;left:0;right:0;height:3px;background:var(--border);z-index:1}
.step{position:relative;z-index:2;text-align:center;flex:1}
.step-icon{width:32px;height:32px;background:var(--bg3);border:2px solid var(--border);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;font-size:.8rem;color:var(--text3)}
.step.active .step-icon{background:var(--gold);border-color:var(--gold);color:#000;box-shadow:0 0 10px rgba(255,215,0,.3)}
.step.done .step-icon{background:var(--green);border-color:var(--green);color:#fff}
.step-label{font-size:.72rem;font-weight:600;color:var(--text3)}
.step.active .step-label,.step.done .step-label{color:var(--text)}
.mon-stats{display:flex;gap:20px;margin-top:15px;font-size:.78rem;background:var(--bg3);padding:10px 15px;border-radius:8px;flex-wrap:wrap}
.mon-stat-item{display:flex;align-items:center;gap:6px}
.mon-stat-val{font-weight:700;color:var(--gold)}
.invoice-box{background:#0d2318;border:1px solid var(--green3);border-radius:8px;padding:12px 14px;margin-top:14px;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
.invoice-box .total{color:var(--gold);font-size:1rem;font-weight:800}
.muted-note{color:var(--text3);font-size:.75rem;line-height:1.5}
</style>

<div class="sec-title">📊 Monitoring Progress Sampel</div>

<?php if ($msg): ?>
    <div class="alert-box <?= str_starts_with($msg,'ERROR')?'alert-red':'alert-green' ?>" style="margin-bottom:14px">
        <?= bersihkan($msg) ?>
    </div>
<?php endif; ?>

<?php if (!clientAccessTableReady($pdo)): ?>
    <div class="alert-box alert-red">Tabel client_access belum tersedia. Admin perlu menjalankan scripts/sql/patch_client_role.sql.</div>
<?php elseif (!$accessList): ?>
    <div class="card" style="text-align:center;padding:30px;color:var(--text3)">Belum ada sampel yang terhubung ke akun ini.</div>
<?php endif; ?>

<?php foreach ($accessList as $access): ?>
    <?php
    $penerimaanId = (int)($access['penerimaan_id'] ?? 0);
    $samples = [];
    $results = [];
    $invoice = null;
    $totalSamples = 0;
    $doneSamples = 0;
    $allDone = false;

    if ($penerimaanId) {
        $stSamples = $pdo->prepare("
            SELECT id, kode_sampel, jenis_material, berat_gram, metode_uji, status, keterangan
            FROM sampel
            WHERE penerimaan_id = ?
            ORDER BY kode_sampel
        ");
        $stSamples->execute([$penerimaanId]);
        $samples = $stSamples->fetchAll();
        $totalSamples = count($samples);
        $doneSamples = count(array_filter($samples, fn($s) => $s['status'] === 'selesai'));
        $allDone = $totalSamples > 0 && $doneSamples === $totalSamples;

        $stResults = $pdo->prepare("
            SELECT h.*, s.kode_sampel
            FROM hasil_uji h
            JOIN sampel s ON s.id = h.sampel_id
            WHERE s.penerimaan_id = ?
            ORDER BY s.kode_sampel, h.parameter
        ");
        $stResults->execute([$penerimaanId]);
        $results = $stResults->fetchAll();

        if ($allDone) {
            $stInv = $pdo->prepare("
                SELECT *
                FROM invoice
                WHERE penerimaan_id = ?
                  AND status IN ('diterbitkan','lunas')
                ORDER BY FIELD(status,'diterbitkan','lunas'), created_at DESC
                LIMIT 1
            ");
            $stInv->execute([$penerimaanId]);
            $invoice = $stInv->fetch();
        }
    } elseif (!empty($access['submission_id']) && tableExists($pdo, 'submission_sampel_detail')) {
        $stSubSamples = $pdo->prepare("
            SELECT jenis_material, berat_gram, metode_uji, parameter, keterangan
            FROM submission_sampel_detail
            WHERE submission_id = ?
            ORDER BY id
        ");
        $stSubSamples->execute([(int)$access['submission_id']]);
        $samples = $stSubSamples->fetchAll();
        $totalSamples = count($samples);
    }

    $progress = $totalSamples > 0 ? round(($doneSamples / $totalSamples) * 100) : 0;
    $mainCode = $access['nomor_penerimaan'] ?: ($access['nomor_submission'] ?: $access['kode_akses']);
    ?>

    <div class="mon-card">
        <div class="mon-header">
            <div>
                <div class="mon-title"><?= bersihkan($mainCode) ?> — <?= bersihkan($access['klien']) ?></div>
                <div class="mon-meta">Submission: <?= bersihkan($access['nomor_submission'] ?? $access['kode_akses']) ?> | Total Sampel: <strong><?= $totalSamples ?></strong></div>
            </div>
            <div>
                <?php if ($penerimaanId): ?>
                    <?= badgeStatus($access['penerimaan_status']) ?>
                <?php else: ?>
                    <?= badgeStatus($access['submission_status'] ?? 'pending') ?>
                <?php endif; ?>
            </div>
        </div>

        <?php
        $s1 = 'done';
        $s2 = $penerimaanId ? 'done' : 'active';
        $s3 = ($penerimaanId && $doneSamples > 0) ? 'done' : ($penerimaanId ? 'active' : 'pending');
        $s4 = $allDone ? 'done' : 'pending';
        ?>
        <div class="stepper">
            <div class="step <?= $s1 ?>"><div class="step-icon">1</div><div class="step-label">Pengajuan</div></div>
            <div class="step <?= $s2 ?>"><div class="step-icon">2</div><div class="step-label">Penerimaan</div></div>
            <div class="step <?= $s3 ?>"><div class="step-icon">3</div><div class="step-label">Pengujian</div></div>
            <div class="step <?= $s4 ?>"><div class="step-icon">4</div><div class="step-label">Selesai</div></div>
        </div>

        <div class="mon-stats">
            <div class="mon-stat-item">📦 Penerimaan: <span class="mon-stat-val"><?= $penerimaanId ? 'Masuk' : 'Menunggu' ?></span></div>
            <div class="mon-stat-item">🔬 Pengujian: <span class="mon-stat-val"><?= $doneSamples ?>/<?= $totalSamples ?></span></div>
            <div class="mon-stat-item">🏁 Progress: <span class="mon-stat-val"><?= $progress ?>%</span></div>
        </div>

        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sampel</th>
                        <th>Material</th>
                        <th>Berat</th>
                        <th>Metode</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($samples as $i => $sample): ?>
                    <tr>
                        <td style="font-weight:700;color:var(--gold)">
                            <?= bersihkan($sample['kode_sampel'] ?? ('Submission #' . ($i + 1))) ?>
                        </td>
                        <td><?= bersihkan($sample['jenis_material'] ?? '-') ?></td>
                        <td><?= !empty($sample['berat_gram']) ? number_format((float)$sample['berat_gram'], 3, ',', '.') . ' g' : '-' ?></td>
                        <td><?= bersihkan($sample['metode_uji'] ?? '-') ?></td>
                        <td><?= isset($sample['status']) ? badgeStatus($sample['status']) : badgeStatus($access['submission_status'] ?? 'pending') ?></td>
                        <td style="font-size:.75rem;color:var(--text3)"><?= bersihkan($sample['keterangan'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$samples): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--text3);padding:16px">Belum ada detail sampel.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($results): ?>
            <div class="card-title" style="margin-top:16px">&#128300; Hasil Uji Tersedia <span><?= count($results) ?> parameter</span></div>
            <div style="overflow-x:auto">
                <table class="data-table">
                    <thead><tr><th>Sampel</th><th>Parameter</th><th>Nilai</th><th>Satuan</th><th>Metode</th><th>Kesimpulan</th></tr></thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                        <tr>
                            <td><?= bersihkan($result['kode_sampel']) ?></td>
                            <td><?= bersihkan($result['parameter']) ?></td>
                            <td><strong><?= bersihkan($result['nilai']) ?></strong></td>
                            <td><?= bersihkan($result['satuan']) ?></td>
                            <td><?= bersihkan($result['metode']) ?></td>
                            <td><?= badgeStatus($result['kesimpulan']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($allDone && $invoice): ?>
            <div class="invoice-box">
                <div>
                    <div style="font-weight:800;color:var(--green)">Invoice Tersedia</div>
                    <div class="muted-note"><?= bersihkan($invoice['nomor_invoice']) ?> · Status <?= bersihkan(ucfirst($invoice['status'])) ?></div>
                </div>
                <div class="total">Rp <?= number_format((float)$invoice['total'], 0, ',', '.') ?></div>
                <a href="<?= BASE_URL ?>/exports/cetak_invoice.php?id=<?= $invoice['id'] ?>" target="_blank" class="btn btn-gold btn-sm">&#128196; Cetak Invoice</a>
            </div>
        <?php elseif ($allDone): ?>
            <div class="invoice-box" style="border-color:var(--gold);background:#2d1a00">
                <div>
                    <div style="font-weight:800;color:var(--gold)">Pengujian Selesai</div>
                    <div class="muted-note">Invoice belum diterbitkan oleh admin. Invoice akan muncul otomatis di sini setelah diterbitkan.</div>
                </div>
            </div>
        <?php else: ?>
            <div class="muted-note" style="margin-top:12px">Invoice akan tampil setelah semua sampel selesai diuji.</div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
