<?php
/**
 * REST API Endpoint: XRF Explorer 7000 Connection Test & Ingestion
 * Path: /api/api_xrf_receive.php
 */

header('Content-Type: application/json; charset=utf-8');

// Enable error reporting for debug
error_reporting(E_ALL);
ini_set('display_errors', 0);

// =================================================================
// ⚙️ CONFIGURATION & MODE
// =================================================================
// Set CONNECTION_TEST_ONLY to true: Only checks connection, does NOT save to MySQL DB.
// Set CONNECTION_TEST_ONLY to false: Saves incoming data to MySQL DB.
define('CONNECTION_TEST_ONLY', false);

// Secret API Key (must match the API Key configured in Android App)
define('XRF_SECRET_KEY', 'xrf_secret_labmineral_2026');

// File path to store recent connection logs for dashboard display
define('LOG_FILE_PATH', __DIR__ . '/xrf_connection_log.json');

$client_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$current_time = date('Y-m-d H:i:s');

// 0. Handle HTTP GET request (when user opens URL directly in web browser)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(200);
    echo json_encode([
        'status'         => 'online',
        'service'        => 'XRF Explorer 7000 API Receiver',
        'mode'           => CONNECTION_TEST_ONLY ? 'CONNECTION_TEST_ONLY' : 'DATABASE_SAVING_ENABLED',
        'message'        => 'API Receiver is ONLINE and listening for HTTP POST JSON requests from XRF Explorer 7000.',
        'dashboard_url'  => 'https://silab.aispektra.com/pages/xrf_dashboard.php'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// 1. Get raw HTTP POST JSON payload
$raw_input = file_get_contents('php://input');

if (empty($raw_input)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Empty POST payload received from IP: ' . $client_ip . '. Ensure request method is POST with JSON body.'
    ], JSON_PRETTY_PRINT);
    exit;
}

$data = json_decode($raw_input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON payload: ' . json_last_error_msg()
    ], JSON_PRETTY_PRINT);
    exit;
}

// 2. Security API Key Check
$provided_key = isset($data['api_key']) ? $data['api_key'] : '';
if (!empty(XRF_SECRET_KEY) && $provided_key !== XRF_SECRET_KEY) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized: Invalid API Key from IP: ' . $client_ip
    ], JSON_PRETTY_PRINT);
    exit;
}

// 3. Extract Payload Metadata
$device_id       = $data['device_id'] ?? 'XRF-7000';
$db_source       = $data['db_source'] ?? 'connection_test';
$report_id       = intval($data['report_id'] ?? 0);
$sample_name     = trim($data['sample_name'] ?? 'PING_TEST');
$sample_supplier = trim($data['sample_supplier'] ?? '');
$work_curve      = trim($data['work_curve_name'] ?? '-');
$operator        = trim($data['operator'] ?? 'operator');
$device_type     = trim($data['device_type'] ?? 'XRF Explorer');
$spectrum_name   = trim($data['spectrum_name'] ?? '');
$test_point      = intval($data['test_point'] ?? 1);

$elements_cnt= isset($data['elements']) && is_array($data['elements']) ? count($data['elements']) : 0;

// 4. Log Connection Event to JSON File (for live UI display)
$log_entry = [
    'timestamp'    => $current_time,
    'client_ip'    => $client_ip,
    'device_id'    => $device_id,
    'db_source'    => $db_source,
    'report_id'    => $report_id,
    'sample_name'  => $sample_name,
    'work_curve'   => $work_curve,
    'operator'     => $operator,
    'elements_cnt' => $elements_cnt,
    'elements'     => isset($data['elements']) ? $data['elements'] : [],
    'status'       => 'CONNECTED_OK'
];

$existing_logs = [];
if (file_exists(LOG_FILE_PATH)) {
    $raw_logs = file_get_contents(LOG_FILE_PATH);
    $existing_logs = json_decode($raw_logs, true) ?: [];
}
// Keep last 50 log entries
array_unshift($existing_logs, $log_entry);
$existing_logs = array_slice($existing_logs, 0, 50);
@file_put_contents(LOG_FILE_PATH, json_encode($existing_logs, JSON_PRETTY_PRINT));

// =================================================================
//  MODE 1: CONNECTION TEST ONLY (DB SAVING DISABLED)
// =================================================================
if (CONNECTION_TEST_ONLY) {
    http_response_code(200);
    echo json_encode([
        'status'       => 'success',
        'mode'         => 'connection_test_only',
        'message'      => 'Koneksi Wi-Fi dari XRF Explorer 7000 BERHASIL (Data TIDAK disimpan ke DB)',
        'connection'   => [
            'client_ip'     => $client_ip,
            'device_id'     => $device_id,
            'received_at'   => $current_time,
            'sample_name'   => $sample_name,
            'db_source'     => $db_source,
            'report_id'     => $report_id,
            'elements_cnt'  => $elements_cnt
        ]
    ], JSON_PRETTY_PRINT);
    exit;
}

// =================================================================
//  MODE 2: DATABASE SAVING ENABLED (MySQL)
// =================================================================
$db_host = 'localhost';
$db_name = 'labmineral';
$db_user = 'root';
$db_pass = '';

if (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
} elseif (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
}

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $db = $pdo;
    } else {
        $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }

    // Auto-create XRF tables if not yet migrated on the server
    $db->exec("
        CREATE TABLE IF NOT EXISTS `xrf_devices` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `device_id` VARCHAR(50) NOT NULL UNIQUE,
            `device_name` VARCHAR(100) NOT NULL,
            `device_type` VARCHAR(50) DEFAULT 'XRF Explorer',
            `last_seen_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `xrf_measurements` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `device_id` VARCHAR(50) NOT NULL DEFAULT 'XRF-7000',
            `db_source` VARCHAR(50) NOT NULL COMMENT 'metal.db, alloy.db, or mineral.db',
            `report_id` INT NOT NULL COMMENT 'HistoryReportID from XRF SQLite',
            `sample_name` VARCHAR(100) NOT NULL,
            `sample_supplier` VARCHAR(100) DEFAULT NULL,
            `test_date` DATETIME DEFAULT NULL,
            `timestamp_ms` BIGINT DEFAULT NULL,
            `test_time` INT DEFAULT NULL,
            `tub_voltage` FLOAT DEFAULT NULL,
            `tub_current` FLOAT DEFAULT NULL,
            `work_curve_name` VARCHAR(100) DEFAULT NULL,
            `grade` VARCHAR(100) DEFAULT NULL,
            `operator` VARCHAR(50) DEFAULT NULL,
            `gps` VARCHAR(100) DEFAULT '(0.0,0.0)',
            `longitude` DOUBLE DEFAULT 0,
            `latitude` DOUBLE DEFAULT 0,
            `altitude` DOUBLE DEFAULT 0,
            `cps` INT DEFAULT 0,
            `counts` INT DEFAULT 0,
            `temperature` FLOAT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_device_report` (`device_id`, `db_source`, `report_id`, `timestamp_ms`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `xrf_measurement_elements` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `measurement_id` INT NOT NULL,
            `element_name` VARCHAR(10) NOT NULL,
            `concentration` DOUBLE NOT NULL DEFAULT 0,
            `element_error` DOUBLE DEFAULT 0,
            `unit` VARCHAR(20) DEFAULT '%',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_meas_id` (`measurement_id`),
            INDEX `idx_element_search` (`measurement_id`, `element_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Auto-heal / add any missing columns in xrf_measurements and xrf_devices
    $cols_to_add = [
        'sample_supplier'    => 'VARCHAR(100) DEFAULT NULL',
        'device_type'        => 'VARCHAR(50) DEFAULT "XRF Explorer"',
        'spectrum_name'      => 'VARCHAR(100) DEFAULT NULL',
        'test_point'         => 'INT DEFAULT 1',
        'peak'               => 'INT DEFAULT 0',
        'fwhm'               => 'INT DEFAULT 0',
        'ms8607_pressure'    => 'DOUBLE DEFAULT 0',
        'ms8607_temperature' => 'DOUBLE DEFAULT 0',
        'ms8607_humidity'    => 'DOUBLE DEFAULT 0',
        'client_ip'          => 'VARCHAR(45) DEFAULT NULL',
        'received_at'        => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
    ];
    foreach ($cols_to_add as $col => $col_def) {
        try {
            $db->exec("ALTER TABLE `xrf_measurements` ADD COLUMN `$col` $col_def");
        } catch (Exception $ignCol) {}
    }
    try {
        $db->exec("ALTER TABLE `xrf_devices` ADD COLUMN `device_type` VARCHAR(50) DEFAULT 'XRF Explorer'");
    } catch (Exception $ignDevCol) {}

    $db->beginTransaction();
    
    // Auto-register device to xrf_devices to update last_seen_at (Safe try-catch)
    try {
        $dev_stmt = $db->prepare("INSERT INTO xrf_devices (device_id, device_name, device_type, last_seen_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_seen_at = NOW()");
        $dev_stmt->execute([$device_id, 'Alat XRF - ' . $device_id, $device_type ?: 'XRF Explorer']);
    } catch (Exception $ignDev) {
        try {
            $upd_dev = $db->prepare("UPDATE xrf_devices SET last_seen_at = NOW() WHERE device_id = ?");
            $upd_dev->execute([$device_id]);
        } catch (Exception $ign) {}
    }
    
    // Stop processing if this is just a heartbeat ping
    if ($db_source === 'ping') {
        $db->commit();
        echo json_encode([
            'status'  => 'success',
            'mode'    => 'heartbeat',
            'message' => 'Heartbeat received, device status updated.'
        ], JSON_PRETTY_PRINT);
        exit;
    }

    $test_date_val = !empty($data['test_date']) ? $data['test_date'] : date('Y-m-d H:i:s');
    
    // Ignore data older than August 26, 2026
    if (strtotime($test_date_val) < strtotime('2026-08-26 00:00:00')) {
        $db->commit();
        echo json_encode([
            'status'  => 'success',
            'mode'    => 'ignored_old_data',
            'message' => 'Data from before Aug 26, 2026 is ignored per user request.'
        ], JSON_PRETTY_PRINT);
        exit;
    }

    $test_date          = !empty($data['test_date']) ? $data['test_date'] : date('Y-m-d H:i:s');
    $timestamp_ms       = floatval($data['timestamp_ms'] ?? 0);

    $check_stmt = $db->prepare("SELECT id FROM xrf_measurements WHERE device_id = ? AND db_source = ? AND report_id = ? AND timestamp_ms = ?");
    $check_stmt->execute([$device_id, $db_source, $report_id, $timestamp_ms]);
    $existing = $check_stmt->fetch();
    $test_time          = intval($data['test_time'] ?? 0);
    $tub_voltage        = floatval($data['tub_voltage'] ?? 0);
    $tub_current        = floatval($data['tub_current'] ?? 0);
    $grade              = trim($data['grade'] ?? '');
    $gps                = trim($data['gps'] ?? '(0.0,0.0)');
    $longitude          = floatval($data['longitude'] ?? 0);
    $latitude           = floatval($data['latitude'] ?? 0);
    $altitude           = floatval($data['altitude'] ?? 0);
    $cps                = intval($data['cps'] ?? 0);
    $counts             = intval($data['counts'] ?? 0);
    $temperature        = floatval($data['temperature'] ?? 0);
    
    // New Columns
    $peak               = intval($data['peak'] ?? 0);
    $fwhm               = intval($data['fwhm'] ?? 0);
    $ms8607_pressure    = floatval($data['ms8607_pressure'] ?? 0);
    $ms8607_temperature = floatval($data['ms8607_temperature'] ?? 0);
    $ms8607_humidity    = floatval($data['ms8607_humidity'] ?? 0);
    
    $elements           = isset($data['elements']) && is_array($data['elements']) ? $data['elements'] : [];

    if ($existing) {
        $measurement_id = $existing['id'];
        $update_stmt = $db->prepare("UPDATE xrf_measurements SET 
            sample_name = ?, sample_supplier = ?, test_date = ?, timestamp_ms = ?, test_time = ?,
            tub_voltage = ?, tub_current = ?, work_curve_name = ?, grade = ?, operator = ?,
            device_type = ?, spectrum_name = ?, test_point = ?,
            gps = ?, longitude = ?, latitude = ?, altitude = ?, cps = ?, counts = ?, temperature = ?,
            peak = ?, fwhm = ?, ms8607_pressure = ?, ms8607_temperature = ?, ms8607_humidity = ?,
            client_ip = ?, received_at = NOW()
            WHERE id = ?");
        $update_stmt->execute([
            $sample_name, $sample_supplier, $test_date, $timestamp_ms, $test_time,
            $tub_voltage, $tub_current, $work_curve, $grade, $operator,
            $device_type, $spectrum_name, $test_point,
            $gps, $longitude, $latitude, $altitude, $cps, $counts, $temperature,
            $peak, $fwhm, $ms8607_pressure, $ms8607_temperature, $ms8607_humidity,
            $client_ip,
            $measurement_id
        ]);
        $del_elm = $db->prepare("DELETE FROM xrf_measurement_elements WHERE measurement_id = ?");
        $del_elm->execute([$measurement_id]);
        $action = 'updated';
    } else {
        $ins_stmt = $db->prepare("INSERT INTO xrf_measurements (
            device_id, db_source, report_id, sample_name, sample_supplier, test_date, timestamp_ms, test_time,
            tub_voltage, tub_current, work_curve_name, grade, operator, device_type, spectrum_name, test_point,
            gps, longitude, latitude, altitude, cps, counts, temperature,
            peak, fwhm, ms8607_pressure, ms8607_temperature, ms8607_humidity, client_ip
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins_stmt->execute([
            $device_id, $db_source, $report_id, $sample_name, $sample_supplier, $test_date, $timestamp_ms, $test_time,
            $tub_voltage, $tub_current, $work_curve, $grade, $operator, $device_type, $spectrum_name, $test_point,
            $gps, $longitude, $latitude, $altitude, $cps, $counts, $temperature,
            $peak, $fwhm, $ms8607_pressure, $ms8607_temperature, $ms8607_humidity, $client_ip
        ]);
        $measurement_id = $db->lastInsertId();
        $action = 'inserted';
    }

    if (!empty($elements)) {
        $elm_stmt = $db->prepare("INSERT INTO xrf_measurement_elements (
            measurement_id, element_name, concentration, element_error, unit
        ) VALUES (?, ?, ?, ?, ?)");
        foreach ($elements as $elm) {
            $elm_name = trim($elm['name'] ?? '');
            $elm_con  = floatval($elm['concentration'] ?? 0);
            $elm_err  = floatval($elm['error'] ?? 0);
            $elm_unit = trim($elm['unit'] ?? '%');
            if (!empty($elm_name)) {
                $elm_stmt->execute([$measurement_id, $elm_name, $elm_con, $elm_err, $elm_unit]);
            }
        }
    }

    $db->commit();

    echo json_encode([
        'status'         => 'success',
        'mode'           => 'database_saving_enabled',
        'action'         => $action,
        'measurement_id' => intval($measurement_id),
        'message'        => "XRF measurement successfully saved to database."
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], JSON_PRETTY_PRINT);
}
