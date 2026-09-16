<?php
/**
 * Forwarder: Redirects to the dedicated xrf_php module
 * Path: /pages/xrf_data.php -> /xrf_php/index.php
 */
require_once __DIR__ . '/../config/db.php';
$queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ' . BASE_URL . '/xrf_php/index.php' . $queryString);
exit;
