<?php
// ============================================================
//  laporan.php
// ============================================================
session_start();
require_once __DIR__ . '/config/db.php';
cekLogin();

// Cek akses laporan
if (!canAccessLaporan()) {
    $_SESSION['msg'] = 'ERROR: Hanya Administrator dan Supervisor yang dapat mengakses Laporan.';
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$pageTitle = 'Laporan';

$bln = date('m'); $thn = date('Y');
$selesai  = $pdo->query("SELECT COUNT(*) FROM sampel WHERE status='selesai' AND MONTH(created_at)=$bln AND YEAR(created_at)=$thn")->fetchColumn();
$totalUji = $pdo->query("SELECT COUNT(*) FROM hasil_uji WHERE MONTH(created_at)=$bln AND YEAR(created_at)=$thn")->fetchColumn();
$lulus    = $pdo->query("SELECT COUNT(*) FROM hasil_uji WHERE kesimpulan='lulus' AND MONTH(created_at)=$bln AND YEAR(created_at)=$thn")->fetchColumn();
$pct      = $totalUji > 0 ? round($lulus / $totalUji * 100) : 0;

// Daftar klien untuk quick-link
$klienList = $pdo->query(
    "SELECT DISTINCT klien FROM sampel WHERE klien IS NOT NULL AND klien != '' ORDER BY klien"
)->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/includes/header.php';
?>
<div class="sec-title">Laporan &amp; Export</div>

<div class="grid3">
    <!-- Export PDF per Klien -->
    <div class="card" style="text-align:center">
        <div style="font-size:2rem;margin-bottom:8px">&#128196;</div>
        <div style="color:var(--gold);font-weight:600;margin-bottom:6px">Export PDF per Klien</div>
        <div style="font-size:.75rem;color:var(--text3);margin-bottom:14px">Report of Analysis format sertifikat resmi</div>
        <a href="<?= BASE_URL ?>/exports/export_pdf.php" class="btn btn-red" target="_blank">&#128229; Buka &amp; Pilih Klien</a>
    </div>

    <!-- Export Excel -->
    <div class="card" style="text-align:center">
        <div style="font-size:2rem;margin-bottom:8px">&#128202;</div>
        <div style="color:var(--gold);font-weight:600;margin-bottom:6px">Export Excel Lengkap</div>
        <div style="font-size:.75rem;color:var(--text3);margin-bottom:14px">3 sheet: hasil uji, bahan, peralatan (.xls)</div>
        <a href="<?= BASE_URL ?>/exports/export_excel.php" class="btn btn-green">&#128229; Download Excel</a>
    </div>

    <!-- Statistik bulan ini -->
    <div class="card" style="text-align:center">
        <div style="font-size:2rem;margin-bottom:8px">&#128202;</div>
        <div style="color:var(--gold);font-weight:600;margin-bottom:6px">Statistik <?= date('F Y') ?></div>
        <div style="font-size:1.5rem;font-weight:700;color:var(--green);margin-top:8px"><?= $pct ?>% Lulus</div>
        <div style="font-size:.75rem;color:var(--text3);margin-top:4px"><?= $totalUji ?> total pengujian</div>
    </div>
</div>

<!-- QUICK LINK PER KLIEN -->
<?php if ($klienList): ?>
<div class="card" style="margin-bottom:20px">
    <div class="card-title">&#128101; Cetak Laporan Cepat per Klien</div>
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:4px">
        <?php foreach ($klienList as $k): ?>
            <a href="<?= BASE_URL ?>/exports/export_pdf.php?klien=<?= urlencode($k) ?>"
               target="_blank"
               style="background:var(--bg3);border:1px solid var(--border);color:var(--text2);
                      padding:7px 16px;border-radius:20px;font-size:.78rem;text-decoration:none;
                      transition:.2s;display:inline-flex;align-items:center;gap:6px"
               onmouseover="this.style.borderColor='var(--gold)';this.style.color='var(--gold)'"
               onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text2)'">
                &#128196; <?= bersihkan($k) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- STATISTIK BULANAN -->
<div class="card">
    <div class="card-title">&#128202; Statistik Bulan <?= date('F Y') ?></div>
    <div class="grid2" style="margin-top:8px">
        <div>
            <div class="prog-wrap" style="margin-bottom:10px">
                <div class="prog-label"><span>Sampel Selesai</span><span><?= $selesai ?></span></div>
                <div class="prog"><div class="prog-bar pb-green" style="width:<?= min(100, $selesai * 2) ?>%"></div></div>
            </div>
            <div class="prog-wrap" style="margin-bottom:10px">
                <div class="prog-label"><span>Total Pengujian</span><span><?= $totalUji ?></span></div>
                <div class="prog"><div class="prog-bar pb-gold" style="width:<?= min(100, $totalUji) ?>%"></div></div>
            </div>
            <div class="prog-wrap">
                <div class="prog-label"><span>Tingkat Kelulusan</span><span><?= $pct ?>%</span></div>
                <div class="prog"><div class="prog-bar pb-green" style="width:<?= $pct ?>%"></div></div>
            </div>
        </div>
        <div style="font-size:.82rem;color:var(--text2);line-height:2.2">
            <div>&#128204; Sampel Selesai: <strong style="color:var(--green)"><?= $selesai ?></strong></div>
            <div>&#128300; Total Uji: <strong style="color:var(--gold)"><?= $totalUji ?></strong></div>
            <div>&#10003; Lulus: <strong style="color:var(--green)"><?= $lulus ?></strong></div>
            <div>&#10007; Tidak Lulus: <strong style="color:var(--red)"><?= $totalUji - $lulus ?></strong></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>