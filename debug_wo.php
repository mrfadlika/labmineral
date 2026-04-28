<?php
// debug_wo.php
session_start();
require_once __DIR__ . '/config/db.php';

echo "<h2>Debug Work Order</h2>";

// Cek semua Work Order
$woList = $pdo->query("
    SELECT id, nomor_wo, status, parameter, parameter_list, metode 
    FROM work_order 
    ORDER BY created_at DESC
")->fetchAll();

echo "<h3>Work Order:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Nomor WO</th><th>Status</th><th>Parameter</th><th>Parameter List</th><th>Metode</th></tr>";
foreach ($woList as $wo) {
    echo "<tr>";
    echo "<td>{$wo['id']}</td>";
    echo "<td>{$wo['nomor_wo']}</td>";
    echo "<td>{$wo['status']}</td>";
    echo "<td>{$wo['parameter']}</td>";
    echo "<td>" . htmlspecialchars($wo['parameter_list']) . "</td>";
    echo "<td>{$wo['metode']}</td>";
    echo "</tr>";
}
echo "</table>";

// Cek Work Order aktif
$woAktif = $pdo->query("
    SELECT id, nomor_wo, parameter, parameter_list 
    FROM work_order 
    WHERE status = 'aktif'
")->fetchAll();

echo "<h3>Work Order Aktif:</h3>";
if (empty($woAktif)) {
    echo "<p style='color:red'>TIDAK ADA WORK ORDER AKTIF!</p>";
} else {
    echo "<pre>";
    print_r($woAktif);
    echo "</pre>";
}

// Cek sampel yang tersedia
$sampelAktif = $pdo->query("
    SELECT s.id, s.kode_sampel, rec.nomor_penerimaan
    FROM sampel s
    LEFT JOIN penerimaan_sampel rec ON s.penerimaan_id = rec.id
    WHERE s.status IN ('antrian','diuji')
    LIMIT 10
")->fetchAll();

echo "<h3>Sampel Tersedia:</h3>";
echo "<pre>";
print_r($sampelAktif);
echo "</pre>";
?>