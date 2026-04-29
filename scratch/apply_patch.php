<?php
require_once 'config/db.php';

try {
    $sql = file_get_contents('merge_client_patch.sql');
    // split by ; to run multiple queries if needed, but exec might handle it or not depending on driver
    // Actually, PDO::exec() for multiple statements depends on the driver.
    // Let's use a more robust way:
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
    $pdo->exec($sql);
    echo "SUCCESS: SQL Patch applied.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
