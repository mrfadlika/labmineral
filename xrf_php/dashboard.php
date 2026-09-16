<?php
/**
 * XRF Explorer 7000 Connection Test & Real-time Monitor Page (Versi PHP)
 * Path: /xrf_php/dashboard.php
 */

require_once __DIR__ . '/../config/db.php';

// Cek file log koneksi
$log_files = [
    __DIR__ . '/xrf_connection_log.json',
    __DIR__ . '/../api/xrf_connection_log.json',
    __DIR__ . '/../xrf_connection_log.json',
    __DIR__ . '/../pages/xrf_connection_log.json'
];

$connection_logs = [];
foreach ($log_files as $f) {
    if (file_exists($f)) {
        $raw = @file_get_contents($f);
        $decoded = json_decode($raw, true);
        if (!empty($decoded) && is_array($decoded)) {
            $connection_logs = $decoded;
            break;
        }
    }
}

$latest_log = $connection_logs[0] ?? null;
$total_pings = count($connection_logs);

$total_measurements = 0;
$total_devices = 0;
if (isset($pdo)) {
    try {
        $total_measurements = (int)$pdo->query("SELECT COUNT(*) FROM xrf_measurements")->fetchColumn();
        $total_devices = (int)$pdo->query("SELECT COUNT(*) FROM xrf_devices")->fetchColumn();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XRF Explorer 7000 - Connection Test Monitor (PHP)</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f172a;
            --bg-card: rgba(30, 41, 59, 0.7);
            --bg-card-hover: rgba(51, 65, 85, 0.8);
            --border-color: rgba(255, 255, 255, 0.1);
            --accent-blue: #38bdf8;
            --accent-purple: #818cf8;
            --accent-emerald: #34d399;
            --accent-amber: #f59e0b;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 2rem;
            background-image: 
                radial-gradient(at 0% 0%, rgba(56, 189, 248, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(245, 158, 11, 0.15) 0px, transparent 50%);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 12px;
        }

        .title-group h1 {
            font-size: 1.6rem;
            font-weight: 700;
            background: linear-gradient(135deg, #38bdf8, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .title-group p {
            color: var(--text-secondary);
            font-size: 0.88rem;
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-back {
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid var(--border-color);
            color: #f1f5f9;
        }
        .btn-back:hover {
            background: rgba(51, 65, 85, 0.9);
            border-color: #38bdf8;
        }
        .btn-standalone {
            background: linear-gradient(135deg, #0284c7, #0369a1);
            border: 1px solid #38bdf8;
            color: #fff;
        }
        .btn-standalone:hover {
            filter: brightness(1.1);
        }

        .status-badge {
            background: rgba(52, 211, 153, 0.15);
            color: var(--accent-emerald);
            border: 1px solid rgba(52, 211, 153, 0.3);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background-color: var(--accent-emerald);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--accent-emerald);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.3; }
            100% { opacity: 1; }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.25rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .card-table {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
        }

        .table-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.88rem;
        }

        th {
            background: rgba(15, 23, 42, 0.4);
            padding: 1rem 1.25rem;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.78rem;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        tr:hover {
            background: var(--bg-card-hover);
        }

        .tag-success {
            background: rgba(52, 211, 153, 0.15);
            color: var(--accent-emerald);
            border: 1px solid rgba(52, 211, 153, 0.3);
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .empty-state {
            padding: 3rem;
            text-align: center;
            color: var(--text-secondary);
            line-height: 1.6;
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <div class="title-group">
            <h1>📶 Monitor Koneksi XRF Explorer 7000</h1>
            <p>Real-time Connection &amp; Live Ingestion Monitor | Versi PHP</p>
        </div>
        <div class="header-actions">
            <div class="status-badge">
                <div class="status-dot"></div>
                LIVE LISTENER AKTIF
            </div>
            <a href="index.php" class="btn-nav btn-back">
                &larr; Data XRF (PHP)
            </a>
            <a href="../xrf/index.html" class="btn-nav btn-standalone">
                🚀 Standalone JS App
            </a>
        </div>
    </header>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Sinyal Masuk (Pings)</div>
            <div class="stat-value" style="color: #38bdf8;"><?= number_format($total_pings) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Data di MySQL</div>
            <div class="stat-value" style="color: #34d399;"><?= number_format($total_measurements) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Sinyal Terakhir Terdeteksi</div>
            <div class="stat-value" style="font-size: 1.1rem; margin-top: 6px; color: var(--accent-emerald);">
                <?= $latest_log ? htmlspecialchars($latest_log['timestamp'] ?? '') : 'Belum Ada Sinyal' ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">IP Perangkat Terakhir</div>
            <div class="stat-value" style="font-size: 1.1rem; margin-top: 6px; color: var(--accent-amber);">
                <?= $latest_log ? htmlspecialchars($latest_log['client_ip'] ?? '-') : '-' ?>
            </div>
        </div>
    </div>

    <div class="card-table">
        <div class="table-header">
            <h2 style="font-size: 1.05rem; font-weight: 600;">Log Aktivitas Koneksi Real-time</h2>
            <small style="color: var(--text-secondary);">Otomatis Diperbarui Setiap 4 Detik 🔄</small>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Waktu Diterima</th>
                    <th>IP Perangkat XRF</th>
                    <th>ID Alat</th>
                    <th>File DB Sumber</th>
                    <th>Nama Sampel Terdeteksi</th>
                    <th>Jml Elemen</th>
                    <th>Status Koneksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($connection_logs)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                📡 Menunggu sinyal dari XRF Explorer 7000...<br>
                                Jalankan aplikasi <strong>XRF Sync Service</strong> di alat XRF untuk mentransmisikan data.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($connection_logs as $log): ?>
                        <tr>
                            <td style="white-space: nowrap; font-weight: 500;">
                                <?= htmlspecialchars($log['timestamp'] ?? '—') ?>
                            </td>
                            <td style="font-family: monospace; color: var(--accent-blue);">
                                <?= htmlspecialchars($log['client_ip'] ?? '—') ?>
                            </td>
                            <td><strong><?= htmlspecialchars($log['device_id'] ?? 'XRF-7000') ?></strong></td>
                            <td><code><?= htmlspecialchars($log['db_source'] ?? '—') ?></code></td>
                            <td><?= htmlspecialchars($log['sample_name'] ?? '—') ?></td>
                            <td><?= intval($log['elements_cnt'] ?? 0) ?> Elemen</td>
                            <td>
                                <span class="tag-success">✓ <?= htmlspecialchars($log['status'] ?? 'OK') ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Auto-refresh page every 4 seconds to show incoming pings in real-time
setTimeout(function() {
    window.location.reload();
}, 4000);
</script>
</body>
</html>
