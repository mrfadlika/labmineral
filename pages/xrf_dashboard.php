<?php
/**
 * Forwarder: Redirects to the dedicated xrf_php dashboard
 * Path: /pages/xrf_dashboard.php -> /xrf_php/dashboard.php
 */
require_once __DIR__ . '/../config/db.php';
$queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ' . BASE_URL . '/xrf_php/dashboard.php' . $queryString);
exit;
