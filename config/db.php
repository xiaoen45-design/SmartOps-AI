<?php
/**
 * SmartOps Source: config/db.php
 * Purpose: MySQL database connection configuration.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */
date_default_timezone_set('Asia/Kuala_Lumpur');

/* SmartOps shared PHP + MySQL configuration.
   Every admin and technician page uses this single database connection.
   Local database settings can be supplied through environment variables or config/local.php.

   Snapshot behaviour:
   - The bundled SQL snapshot is imported automatically on a fresh install.
   - FastAPI refreshes that SQL file after every successfully saved complaint.
   - A newer packaged snapshot version replaces an older local demo dataset.
   - Set SMARTOPS_AUTO_INSTALL=0 to disable automatic installation. */
// Optional local-only configuration. Copy config/local.example.php to
// config/local.php for XAMPP development. The real local.php is git-ignored.
$SMARTOPS_LOCAL_CONFIG = [];
$localConfigFile = __DIR__ . '/local.php';
if (is_file($localConfigFile)) {
    $loadedLocalConfig = require $localConfigFile;
    if (is_array($loadedLocalConfig)) {
        $SMARTOPS_LOCAL_CONFIG = $loadedLocalConfig;
    }
}

$DB_HOST = getenv('SMARTOPS_DB_HOST') ?: (string)($SMARTOPS_LOCAL_CONFIG['db_host'] ?? 'localhost');
$DB_NAME = getenv('SMARTOPS_DB_NAME') ?: (string)($SMARTOPS_LOCAL_CONFIG['db_name'] ?? 'smartops');
$DB_USER = getenv('SMARTOPS_DB_USER') ?: (string)($SMARTOPS_LOCAL_CONFIG['db_user'] ?? 'root');
$DB_PASS_ENV = getenv('SMARTOPS_DB_PASSWORD');
$DB_PASS = $DB_PASS_ENV !== false
    ? $DB_PASS_ENV
    : (string)($SMARTOPS_LOCAL_CONFIG['db_password'] ?? '');
$DB_CHARSET = 'utf8mb4';


// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/demo_bootstrap.php';
smartops_ensure_demo_database($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET);


// =============================================================================
// SECTION: SmartOps PDO Connection
// =============================================================================
function smartops_pdo(): PDO {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET;
    static $pdo = null;

    if ($pdo instanceof PDO) return $pdo;

    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // Use Malaysia time consistently for real-time assignment and SLA timestamps.
    $pdo->exec("SET time_zone = '+08:00'");

    return $pdo;
}
