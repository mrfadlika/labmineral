<?php
// ============================================================
//  client_monitoring.php — Portal monitoring sampel untuk client
// ============================================================
session_start();
require_once __DIR__ . '/config/db.php';
cekLogin();

if (!isClient()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
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

require_once __DIR__ . '/includes/header.php';
?>

<style>
.client-hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:16px;flex-wrap:wrap}
.client-hero h2{margin:0;color:var(--gold);font-size:1rem}
.client-hero p{margin:5px 0 0;color:var(--text3);font-size:.78rem;max-width:680px}
.monitor-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:16px}
.monitor-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;border-bottom:1px solid var(--border);padding-bottom:12px;margin-bottom:14px}
.monitor-code{font-family:monospace;color:var(--gold);font-weight:700;font-size:.95rem}
.meta-grid{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:10px;margin-bottom:14px}
.meta-box{background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:10px 12px}
.meta-box .lbl{font-size:.68rem;color:var(--text3);margin-bottom:4px}
.meta-box .val{font-size:.86rem;color:var(--text);font-weight:700}
.progress-wrap{height:8px;background:var(--bg3);border-radius:999px;overflow:hidden;border:1px solid var(--border)}
.progress-bar{height:100%;background:var(--gold);border-radius:999px}
.stage-row{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:12px 0 16px}
.stage{border:1px solid var(--border);border-radius:8px;padding:9px 10px;background:var(--bg3);font-size:.72rem;color:var(--text3)}
.stage.done{border-color:var(--green3);color:var(--green);background:#0d2318}
.invoice-box{background:#0d2318;border:1px solid var(--green3);border-radius:8px;padding:12px 14px;margin-top:14px;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
.invoice-box .total{color:var(--gold);font-size:1rem;font-weight:800}
.muted-note{color:var(--text3);font-size:.75rem;line-height:1.5}
@media(max-width:900px){.meta-grid,.stage-row{grid-template-columns:1fr 1fr}}
@media(max-width:560px){.meta-grid,.stage-row{grid-template-columns:1fr}}
</style>

<div class="client-hero">
    <div>
        <h2>Monitoring Sampel</h2>
        <p>Gunakan halaman ini untuk melihat posisi pengajuan, penerimaan sampel, status pengujian, hasil yang sudah tersedia, dan invoice ketika pengujian selesai.</p>
    </div>
    <span class="user-chip">&#128100; <?= bersihkan($_SESSION['nama'] ?? '') ?></span>
</div>

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

    <div class="monitor-card">
        <div class="monitor-head">
            <div>
                <div class="monitor-code"><?= bersihkan($mainCode) ?></div>
                <div class="muted-note"><?= bersihkan($access['klien']) ?></div>
            </div>
            <div>
                <?php if ($penerimaanId): ?>
                    <?= badgeStatus($access['penerimaan_status']) ?>
                <?php else: ?>
                    <?= badgeStatus($access['submission_status'] ?? 'pending') ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-box">
                <div class="lbl">No. Submission</div>
                <div class="val"><?= bersihkan($access['nomor_submission'] ?? $access['kode_akses']) ?></div>
            </div>
            <div class="meta-box">
                <div class="lbl">No. Penerimaan</div>
                <div class="val"><?= bersihkan($access['nomor_penerimaan'] ?? 'Belum diproses') ?></div>
            </div>
            <div class="meta-box">
                <div class="lbl">Jumlah Sampel</div>
                <div class="val"><?= $totalSamples ?> sampel</div>
            </div>
            <div class="meta-box">
                <div class="lbl">Progress Uji</div>
                <div class="val"><?= $doneSamples ?>/<?= $totalSamples ?> selesai</div>
            </div>
        </div>

        <div class="progress-wrap" title="<?= $progress ?>%">
            <div class="progress-bar" style="width:<?= $progress ?>%"></div>
        </div>

        <div class="stage-row">
            <div class="stage done">1. Pengajuan diterima</div>
            <div class="stage <?= $penerimaanId ? 'done' : '' ?>">2. Sampel diterima lab</div>
            <div class="stage <?= $penerimaanId && $doneSamples > 0 ? 'done' : '' ?>">3. Pengujian berjalan</div>
            <div class="stage <?= $allDone ? 'done' : '' ?>">4. Selesai & invoice</div>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
