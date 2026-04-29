<?php
// ============================================================
//  includes/header.php
// ============================================================
if (!isset($pageTitle)) $pageTitle = 'LabMineral Pro';

// Hitung alert dengan fungsi terpusat
global $pdo;
$alertData  = ($pdo && !isClient()) ? hitungAlert($pdo) : ['total' => 0];
$alertCount = $alertData['total'];

// Ambil role dari session (dukung kedua format)
$userRole = currentUserRole() ?? 'guest';
$userNama = $_SESSION['nama'] ?? $_SESSION['user_nama'] ?? $_SESSION['name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= bersihkan($pageTitle) ?> &mdash; LabMineral Pro</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
    <style>
        .user-role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            margin-left: 8px;
        }
        .user-role-badge.admin { background: #e74c3c; color: white; }
        .user-role-badge.analis { background: #2ecc71; color: white; }
        .user-role-badge.supervisor { background: #f39c12; color: white; }
        .user-role-badge.klien { background: #3498db; color: white; }
        .user-role-badge.client { background: #3498db; color: white; }
        .user-role-badge.guest { background: #95a5a6; color: white; }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div id="main">
    <div id="topbar">
        <h1><?= bersihkan($pageTitle) ?></h1>
        <div class="topbar-right">
            <?php if ($alertCount > 0): ?>
                <span class="badge-alert" title="<?= $alertData['bahan_rendah'] ?> bahan rendah · <?= $alertData['alat_masalah'] ?> alat bermasalah · <?= $alertData['kalibrasi'] ?> kalibrasi">
                    &#9888; <?= $alertCount ?> Alert
                </span>
            <?php endif; ?>
            <span class="user-chip">
                &#128100; <?= bersihkan($userNama) ?>
                <span class="user-role-badge <?= $userRole ?>"><?= strtoupper($userRole) ?></span>
            </span>
        </div>
    </div>
    <div id="content">
