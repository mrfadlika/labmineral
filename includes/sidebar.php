<?php
// ============================================================
//  includes/sidebar.php — #2: menu Sampel dihapus
// ============================================================
if (isClient()) {
    $menu = [
        ['href' => 'client_monitoring.php', 'ico' => '&#128065;', 'label' => 'Monitoring Sampel'],
    ];
} else {
    $menu = [
        ['href' => 'dashboard.php',   'ico' => '&#128202;', 'label' => 'Dashboard'],
        ['href' => 'penerimaan.php',  'ico' => '&#128230;', 'label' => 'Penerimaan Sampel'],
        ['href' => 'work_order.php',  'ico' => '&#128203;', 'label' => 'Work Order'],
        ['href' => 'preparasi.php',   'ico' => '&#128260;', 'label' => 'Preparasi'],
        ['href' => 'pengujian.php',   'ico' => '&#128300;', 'label' => 'Pengujian'],
        ['href' => 'qc.php',          'ico' => '&#10003;',  'label' => 'QC &amp; Validasi'],
        ['href' => 'invoice.php',     'ico' => '&#128179;', 'label' => 'Invoice'],
        ['href' => 'bahan.php',       'ico' => '&#129514;', 'label' => 'Inventaris Bahan'],
        ['href' => 'peralatan.php',   'ico' => '&#9881;',   'label' => 'Peralatan'],
        ['href' => 'laporan.php',     'ico' => '&#128196;', 'label' => 'Laporan'],
    ];
    // Tambahkan menu submission untuk admin
    if (isAdmin()) {
        $menu[] = ['href' => 'submission.php', 'ico' => '&#128203;', 'label' => 'Submission Klien'];
    }
    if (isAdmin()) {
        $menu[] = ['href' => 'pengguna.php', 'ico' => '&#128101;', 'label' => 'Pengguna'];
    }
}
$cur = basename($_SERVER['PHP_SELF']);
?>
<div id="sidebar">
    <div class="logo">
        <h2>&#9879; AISPEKTRA LABORATORY</h2>
        <p>Sistem Informasi Laboratorium</p>
    </div>
    <nav>
        <?php foreach ($menu as $m): ?>
            <a href="<?= BASE_URL ?>/<?= $m['href'] ?>"
               class="<?= $cur === $m['href'] ? 'active' : '' ?>">
                <span class="ico"><?= $m['ico'] ?></span>
                <?= $m['label'] ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        v<?= APP_VERSION ?> &bull; <?= bersihkan($_SESSION['nama'] ?? '') ?><br>
        <a href="<?= BASE_URL ?>/logout.php" style="color:var(--red)">&#128682; Logout</a>
    </div>
</div>
