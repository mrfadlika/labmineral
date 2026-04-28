<?php
// ============================================================
//  dashboard.php — Halaman Dashboard dengan Notifikasi Work Order
//  UPDATE: Layout notifikasi di sebelah kanan, sejajar dengan stok bahan
// ============================================================
session_start();
require_once __DIR__ . '/config/db.php';
cekLogin();

$pageTitle = 'Dashboard';
$userRole = $_SESSION['user_role'] ?? 'guest';
$userNama = $_SESSION['user_nama'] ?? 'User';
$userId = $_SESSION['user_id'] ?? 0;

// ── Statistik Sampel ─────────────────────────────────────────
$sampelAktif   = $pdo->query("SELECT COUNT(*) FROM sampel WHERE status NOT IN('selesai','ditolak')")->fetchColumn();
$sampelSelesai = $pdo->query("SELECT COUNT(*) FROM sampel WHERE status='selesai'")->fetchColumn();
$sampelTotal   = (int)$sampelAktif + (int)$sampelSelesai;
$pctSelesai    = $sampelTotal > 0 ? round($sampelSelesai / $sampelTotal * 100) : 0;

// ── Statistik Pengujian ──────────────────────────────────────
$ujiLulus   = $pdo->query("SELECT COUNT(*) FROM hasil_uji WHERE kesimpulan='lulus'")->fetchColumn();
$ujiPending = $pdo->query("SELECT COUNT(*) FROM hasil_uji WHERE kesimpulan='pending'")->fetchColumn();
$ujiTLulus  = $pdo->query("SELECT COUNT(*) FROM hasil_uji WHERE kesimpulan='tidak_lulus'")->fetchColumn();
$ujiTotal   = (int)$ujiLulus + (int)$ujiPending + (int)$ujiTLulus;
$pctLulus   = $ujiTotal > 0 ? round($ujiLulus / $ujiTotal * 100) : 0;

// ── NOTIFIKASI WORK ORDER UNTUK ANALIS ────────────────────────
$woNotifications = [];
$woCount = 0;

if (isAnalis()) {
    // Ambil Work Order yang ditugaskan ke analis ini dengan status aktif
    $woStmt = $pdo->prepare("
        SELECT w.*, 
               rec.nomor_penerimaan, rec.klien,
               COUNT(wos.sampel_id) AS jumlah_sampel
        FROM work_order w
        LEFT JOIN penerimaan_sampel rec ON w.penerimaan_id = rec.id
        LEFT JOIN work_order_sampel wos ON wos.wo_id = w.id
        WHERE w.analis_id = ? 
          AND w.status = 'aktif'
        GROUP BY w.id
        ORDER BY 
            FIELD(w.prioritas, 'urgent', 'tinggi', 'normal'),
            w.jadwal_mulai ASC
        LIMIT 5
    ");
    $woStmt->execute([$userId]);
    $woNotifications = $woStmt->fetchAll();
    $woCount = count($woNotifications);
}

// ── Alert ─────────────────────────────────────────────────────
$alertData   = hitungAlert($pdo);
$bahanKritis = $alertData['bahan_kritis'];
$alatMasalah = $alertData['alat_masalah'];

$alertBahan  = $pdo->query(
    "SELECT nama, stok, satuan, stok_minimum FROM bahan
     WHERE stok_minimum > 0 AND stok <= stok_minimum
     ORDER BY stok ASC LIMIT 5"
)->fetchAll();

$alertAlat = $pdo->query(
    "SELECT nama, status, masa_berlaku_kalibrasi FROM peralatan
     WHERE status IN('maintenance','rusak') LIMIT 4"
)->fetchAll();

// ── Penerimaan terbaru ────────────────────────────────────────
$penerimaanTerbaru = $pdo->query("
    SELECT p.nomor_penerimaan, p.klien, p.tanggal_terima, p.status,
            p.jumlah_sampel,
            (SELECT COUNT(*) FROM sampel s
             JOIN hasil_uji h ON h.sampel_id=s.id
             WHERE s.penerimaan_id=p.id AND h.kesimpulan='lulus') AS lulus,
            (SELECT COUNT(*) FROM sampel s
             JOIN hasil_uji h ON h.sampel_id=s.id
             WHERE s.penerimaan_id=p.id AND h.kesimpulan='tidak_lulus') AS tlulus
     FROM penerimaan_sampel p
     ORDER BY p.created_at DESC LIMIT 6
")->fetchAll();

// ── Peralatan utama ───────────────────────────────────────────
$alatUtama = $pdo->query(
    "SELECT nama, status, masa_berlaku_kalibrasi FROM peralatan ORDER BY id LIMIT 6"
)->fetchAll();

// ── Chart data ────────────────────────────────────────────────
$chartData = $pdo->query(
    "SELECT DATE(tanggal_uji) AS tgl, COUNT(*) AS jml
     FROM hasil_uji
     WHERE tanggal_uji >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(tanggal_uji) ORDER BY tgl ASC"
)->fetchAll();

// ── Target bulan ini ─────────────────────────────────────────
$selesaiBulan = $pdo->query(
    "SELECT COUNT(*) FROM sampel WHERE status='selesai'
     AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"
)->fetchColumn();
$target      = 50;
$persenTarget = min(100, round($selesaiBulan / $target * 100));

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* Layout Grid untuk 2 kolom */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}

/* Card style */
.stat-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    transition: all 0.2s;
}

.stat-card .label {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text3);
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.stat-card .val {
    font-size: 1.8rem;
    font-weight: 700;
}

.stat-card .val.gold { color: var(--gold); }
.stat-card .val.red { color: var(--red); }
.stat-card .val.yellow { color: var(--yellow); }
.stat-card .val.green { color: var(--green); }

.stat-card .sub {
    font-size: 0.65rem;
    color: var(--text3);
    margin-top: 4px;
}

/* Progress bar */
.prog-wrap {
    margin-top: 8px;
}
.prog-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.65rem;
    color: var(--text3);
    margin-bottom: 4px;
}
.prog {
    background: var(--bg3);
    border-radius: 4px;
    height: 6px;
    overflow: hidden;
}
.prog-bar {
    height: 100%;
    border-radius: 4px;
}
.prog-bar.pb-green { background: var(--green3); }
.prog-bar.pb-gold { background: var(--gold); }

/* Status Pengujian - mini progress */
.mini-progress {
    display: flex;
    height: 6px;
    border-radius: 4px;
    overflow: hidden;
    gap: 2px;
    margin: 8px 0;
}

/* Notifikasi Work Order */
.wo-notification-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.wo-notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 6px;
}
.wo-notification-header h4 {
    margin: 0;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text3);
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.wo-notification-header .badge-count {
    background: var(--gold);
    color: #1a1a2e;
    border-radius: 20px;
    padding: 2px 8px;
    font-size: 0.6rem;
    font-weight: 700;
}
.wo-list {
    flex: 1;
}
.wo-item {
    background: var(--bg3);
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 10px;
    border-left: 3px solid var(--gold);
    transition: all 0.2s;
}
.wo-item:hover {
    transform: translateX(2px);
    background: #1a2e1a;
}
.wo-item.urgent {
    border-left-color: var(--red);
    background: #2a1010;
}
.wo-item.tinggi {
    border-left-color: var(--yellow);
}
.wo-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
    flex-wrap: wrap;
    gap: 4px;
}
.wo-no {
    font-family: monospace;
    font-weight: 700;
    color: var(--gold);
    font-size: 0.8rem;
}
.wo-priority {
    font-size: 0.6rem;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 600;
}
.priority-urgent {
    background: #3d1010;
    color: var(--red);
}
.priority-tinggi {
    background: #3d2e00;
    color: var(--yellow);
}
.priority-normal {
    background: #1a2e1a;
    color: #6aab7e;
}
.wo-detail {
    display: flex;
    gap: 16px;
    font-size: 0.7rem;
    color: var(--text3);
    flex-wrap: wrap;
    margin-bottom: 8px;
}
.wo-detail span {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.wo-action {
    margin-top: 8px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.btn-notif {
    padding: 4px 10px;
    font-size: 0.65rem;
    border-radius: 4px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-notif-primary {
    background: var(--gold);
    color: #1a1a2e;
}
.btn-notif-primary:hover {
    background: #e8b030;
}
.btn-notif-secondary {
    background: var(--bg3);
    border: 1px solid var(--border);
    color: var(--text2);
}
.empty-notif {
    text-align: center;
    padding: 24px 16px;
    color: var(--text3);
    font-size: 0.75rem;
}
.empty-notif span {
    font-size: 2rem;
}
.view-all-link {
    text-align: center;
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid var(--border);
}
.view-all-link a {
    font-size: 0.7rem;
    color: var(--gold);
    text-decoration: none;
}
.view-all-link a:hover {
    text-decoration: underline;
}

/* Alert box */
.alert-box {
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    margin-bottom: 8px;
}
.alert-red {
    background: #3d1010;
    border: 1px solid #7a2020;
    color: #e74c3c;
}
.alert-yellow {
    background: #3d2e00;
    border: 1px solid #5a4000;
    color: var(--yellow);
}
.alert-green {
    background: #1a4d2e;
    border: 1px solid var(--green3);
    color: var(--green);
}

/* Bar chart */
.bar-chart {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    height: 100px;
    margin: 10px 0;
}
.bar-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}
.bar {
    width: 100%;
    background: var(--green3);
    border-radius: 4px;
    transition: height 0.3s;
}
.bar.gold {
    background: var(--gold);
}
.bar-lbl {
    font-size: 0.6rem;
    color: var(--text3);
}

/* Data table */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.7rem;
}
.data-table th,
.data-table td {
    padding: 8px 10px;
    text-align: left;
    border-bottom: 1px solid var(--border);
}
.data-table th {
    color: var(--text3);
    font-weight: 600;
    background: var(--bg3);
}
</style>

<div class="sec-title">Ringkasan Hari Ini &mdash; <?= fmtTglPanjang(date('Y-m-d')) ?></div>

<!-- ── BARIS 1: STATUS SAMPEL + STATUS PENGUJIAN ────────────── -->
<div class="dashboard-grid">
    <!-- Status Sampel -->
    <div class="stat-card">
        <div class="label">STATUS SAMPEL</div>
        <div style="display:flex;gap:20px;align-items:baseline;margin:4px 0 6px">
            <div>
                <span class="val gold"><?= $sampelAktif ?></span>
                <span style="font-size:0.7rem;color:var(--text3);margin-left:4px">Aktif</span>
            </div>
            <div style="color:var(--border);font-size:1rem">/</div>
            <div>
                <span class="val"><?= $sampelSelesai ?></span>
                <span style="font-size:0.7rem;color:var(--text3);margin-left:4px">Selesai</span>
            </div>
        </div>
        <div class="prog-wrap">
            <div class="prog-label">
                <span>Selesai <?= $pctSelesai ?>% dari <?= $sampelTotal ?> total</span>
            </div>
            <div class="prog">
                <div class="prog-bar pb-green" style="width:<?= $pctSelesai ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Status Pengujian -->
    <div class="stat-card">
        <div class="label">STATUS PENGUJIAN</div>
        <div style="display:flex;gap:16px;align-items:baseline;margin:4px 0 6px;flex-wrap:wrap">
            <div>
                <span class="val green"><?= $ujiLulus ?></span>
                <span style="font-size:0.7rem;color:var(--text3);margin-left:3px">Lulus</span>
            </div>
            <div>
                <span class="val yellow"><?= $ujiPending ?></span>
                <span style="font-size:0.7rem;color:var(--text3);margin-left:3px">Pending</span>
            </div>
            <div>
                <span class="val red"><?= $ujiTLulus ?></span>
                <span style="font-size:0.7rem;color:var(--text3);margin-left:3px">Tidak Lulus</span>
            </div>
        </div>
        <?php if ($ujiTotal > 0): ?>
        <div class="mini-progress">
            <div style="width:<?= round($ujiLulus/$ujiTotal*100) ?>%;background:var(--green3)"></div>
            <div style="width:<?= round($ujiPending/$ujiTotal*100) ?>%;background:var(--yellow)"></div>
            <div style="width:<?= round($ujiTLulus/$ujiTotal*100) ?>%;background:var(--red)"></div>
        </div>
        <div class="prog-label">
            <span><?= $pctLulus ?>% tingkat kelulusan dari <?= $ujiTotal ?> pengujian</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── BARIS 2: STOK BAHAN + NOTIFIKASI WORK ORDER ──────────── -->
<div class="dashboard-grid">
    <!-- Stok Bahan Kritis -->
    <div class="stat-card">
        <div class="label">STOK BAHAN KRITIS</div>
        <div class="val red"><?= $bahanKritis ?></div>
        <div class="sub">Perlu pengadaan segera</div>
        <?php if (!empty($alertBahan)): ?>
        <div style="margin-top:12px">
            <?php foreach (array_slice($alertBahan, 0, 3) as $b): ?>
            <div style="font-size:0.7rem; color:var(--text3); margin-bottom:4px">
                • <?= bersihkan($b['nama']) ?>: <?= $b['stok'] ?> <?= bersihkan($b['satuan']) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- NOTIFIKASI WORK ORDER (untuk analis) -->
    <?php if (isAnalis()): ?>
    <div class="wo-notification-card">
        <div class="wo-notification-header">
            <h4>
                <span>🔔</span> NOTIFIKASI WORK ORDER
                <?php if ($woCount > 0): ?>
                <span class="badge-count"><?= $woCount ?> tugas</span>
                <?php endif; ?>
            </h4>
            <span style="font-size:0.6rem; color:var(--text3)">Ditugaskan kepada Anda</span>
        </div>
        
        <?php if ($woCount > 0): ?>
        <div class="wo-list">
            <?php foreach ($woNotifications as $wo): 
                $priorityClass = 'priority-' . $wo['prioritas'];
                $isUrgent = $wo['prioritas'] === 'urgent';
                $isTinggi = $wo['prioritas'] === 'tinggi';
            ?>
            <div class="wo-item <?= $isUrgent ? 'urgent' : ($isTinggi ? 'tinggi' : '') ?>">
                <div class="wo-item-header">
                    <span class="wo-no">📋 <?= bersihkan($wo['nomor_wo']) ?></span>
                    <span class="wo-priority <?= $priorityClass ?>"><?= strtoupper($wo['prioritas']) ?></span>
                </div>
                <div class="wo-detail">
                    <span>🏢 <?= bersihkan($wo['klien'] ?? '—') ?></span>
                    <span>📦 <?= $wo['jumlah_sampel'] ?> sampel</span>
                    <?php if ($wo['jadwal_mulai']): ?>
                    <span>📅 <?= date('d/m', strtotime($wo['jadwal_mulai'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="wo-action">
                    <a href="<?= BASE_URL ?>/preparasi.php?wo_id=<?= $wo['id'] ?>" class="btn-notif btn-notif-primary">⚗️ Preparasi</a>
                    <a href="<?= BASE_URL ?>/pengujian.php?wo_id=<?= $wo['id'] ?>&tab=batch" class="btn-notif btn-notif-primary">🔬 Hasil Uji</a>
                    <a href="<?= BASE_URL ?>/work_order.php?tab=daftar" class="btn-notif btn-notif-secondary">📋 Detail</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($woCount > 3): ?>
        <div class="view-all-link">
            <a href="<?= BASE_URL ?>/work_order.php?tab=daftar">Lihat semua tugas →</a>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="empty-notif">
            <span>✅</span>
            <p style="margin-top:8px">Belum ada Work Order yang ditugaskan.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <!-- Jika bukan analis, tampilkan Alat Bermasalah di sini -->
    <div class="stat-card">
        <div class="label">ALAT BERMASALAH</div>
        <div class="val yellow"><?= $alatMasalah ?></div>
        <div class="sub">Maintenance / Rusak</div>
        <?php if (!empty($alertAlat)): ?>
        <div style="margin-top:12px">
            <?php foreach (array_slice($alertAlat, 0, 3) as $a): ?>
            <div style="font-size:0.7rem; color:var(--text3); margin-bottom:4px">
                • <?= bersihkan($a['nama']) ?>: <?= $a['status'] ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ── BARIS 3: ALERT & CHART (2 kolom) ──────────────────────── -->
<div class="dashboard-grid">
    <!-- Alert Notifikasi -->
    <div class="stat-card">
        <div class="label">⚠️ ALERT &amp; NOTIFIKASI</div>
        <?php foreach ($alertBahan as $b):
            $st = statusBahan($b['stok'], $b['stok_minimum']); ?>
            <div class="alert-box <?= $st==='kritis' ? 'alert-red' : 'alert-yellow' ?>">
                <?= $st==='kritis' ? '🔴' : '🟡' ?>
                <strong><?= bersihkan($b['nama']) ?></strong>
                — stok: <?= $b['stok'] ?> <?= bersihkan($b['satuan']) ?>
            </div>
        <?php endforeach; ?>
        <?php foreach ($alertAlat as $a): ?>
            <div class="alert-box <?= $a['status']==='rusak' ? 'alert-red' : 'alert-yellow' ?>">
                <?= $a['status']==='rusak' ? '🔴' : '🟡' ?>
                <strong><?= bersihkan($a['nama']) ?></strong>
                — <?= bersihkan($a['status']) ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$alertBahan && !$alertAlat): ?>
            <div class="alert-box alert-green">
                ✅ Semua bahan dan peralatan dalam kondisi normal.
            </div>
        <?php endif; ?>
    </div>

    <!-- Chart Pengujian -->
    <div class="stat-card">
        <div class="label">📊 PENGUJIAN PER HARI <span style="font-size:0.6rem">7 hari terakhir</span></div>
        <div class="bar-chart" id="weekchart"></div>
        <div class="prog-wrap" style="margin-top:12px">
            <div class="prog-label">
                <span>Target Bulan Ini (<?= $selesaiBulan ?>/<?= $target ?>)</span>
                <span><?= $persenTarget ?>%</span>
            </div>
            <div class="prog">
                <div class="prog-bar pb-green" style="width:<?= $persenTarget ?>%"></div>
            </div>
        </div>
    </div>
</div>

<!-- ── BARIS 4: PENERIMAAN TERBARU + PERALATAN ──────────────── -->
<div class="dashboard-grid">
    <!-- Penerimaan Terbaru -->
    <div class="stat-card">
        <div class="label">📦 PENERIMAAN TERBARU</div>
        <div style="overflow-x:auto; margin-top:8px">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No. Penerimaan</th><th>Klien</th><th>Tgl</th><th>Sampel</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($penerimaanTerbaru, 0, 5) as $p): ?>
                <tr>
                    <td style="font-weight:700;color:var(--gold);font-size:0.7rem"><?= bersihkan($p['nomor_penerimaan']) ?></td>
                    <td style="font-size:0.7rem"><?= bersihkan($p['klien']) ?></td>
                    <td style="font-size:0.65rem"><?= fmtTgl($p['tanggal_terima']) ?></td>
                    <td style="text-align:center"><?= $p['jumlah_sampel'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$penerimaanTerbaru): ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text3);padding:16px">Belum ada penerimaan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Status Peralatan Utama -->
    <div class="stat-card">
        <div class="label">🔧 STATUS PERALATAN UTAMA</div>
        <div style="overflow-x:auto; margin-top:8px">
        <table class="data-table">
            <thead><tr><th>Alat</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach (array_slice($alatUtama, 0, 5) as $a): ?>
                <tr>
                    <td style="font-size:0.7rem"><?= bersihkan($a['nama']) ?></td>
                    <td><?= badgeStatus($a['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<script>
const cd = <?= json_encode($chartData) ?>;
const ch = document.getElementById('weekchart');
if (cd.length > 0) {
    const mx = Math.max(...cd.map(d => d.jml));
    ch.innerHTML = '';
    cd.forEach((d, i) => {
        const col = document.createElement('div'); col.className = 'bar-col';
        const bar = document.createElement('div');
        bar.className = 'bar' + (i === cd.length - 1 ? ' gold' : '');
        bar.style.height = (d.jml / mx * 80) + 'px';
        const lbl = document.createElement('div'); lbl.className = 'bar-lbl';
        lbl.textContent = d.tgl.slice(5);
        col.appendChild(bar); col.appendChild(lbl); ch.appendChild(col);
    });
} else {
    ch.innerHTML = '<p style="color:var(--color-text-tertiary);font-size:.78rem;padding:10px 0">Belum ada data minggu ini.</p>';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>