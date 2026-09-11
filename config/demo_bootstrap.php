<?php
/**
 * SmartOps Source: config/demo_bootstrap.php
 * Purpose: Database bootstrap and packaged SQL snapshot version synchronization.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */
/**
 * SmartOps SQL snapshot bootstrap.
 *
 * The expected snapshot version is stored in config/snapshot_version.txt.
 * When the packaged SQL is newer than the installed local database, the SQL
 * snapshot is imported automatically so every shared copy starts identically.
 */


// =============================================================================
// SECTION: SmartOps Expected Snapshot Version
// =============================================================================
function smartops_expected_snapshot_version(): string {
    $versionFile = __DIR__ . '/snapshot_version.txt';
    if (!is_file($versionFile) || !is_readable($versionFile)) {
        throw new RuntimeException('Snapshot version file is missing: ' . $versionFile);
    }
    $version = trim((string)file_get_contents($versionFile));
    if ($version === '') {
        throw new RuntimeException('Snapshot version file is empty.');
    }
    return $version;
}


// =============================================================================
// SECTION: SmartOps Demo Import SQL
// =============================================================================
function smartops_demo_import_sql(
    string $host,
    string $user,
    string $password,
    string $sqlFile
): void {
    if (!extension_loaded('mysqli')) {
        throw new RuntimeException('The mysqli PHP extension is required for automatic database setup.');
    }
    if (!is_file($sqlFile) || !is_readable($sqlFile)) {
        throw new RuntimeException('Bundled SQL snapshot file is missing: ' . $sqlFile);
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $mysqli = @new mysqli($host, $user, $password);
    if ($mysqli->connect_errno) {
        throw new RuntimeException('Unable to connect to MySQL: ' . $mysqli->connect_error);
    }

    $mysqli->set_charset('utf8mb4');
    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        $mysqli->close();
        throw new RuntimeException('Bundled SQL snapshot file is empty.');
    }

    if (!$mysqli->multi_query($sql)) {
        $message = $mysqli->error ?: 'Unknown MySQL import error';
        $mysqli->close();
        throw new RuntimeException('Automatic SQL snapshot import failed: ' . $message);
    }

    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
        if (!$mysqli->more_results()) {
            break;
        }
    } while ($mysqli->next_result());

    if ($mysqli->errno) {
        $message = $mysqli->error;
        $mysqli->close();
        throw new RuntimeException('Automatic SQL snapshot import failed: ' . $message);
    }

    $mysqli->close();
}


// =============================================================================
// SECTION: SmartOps Demo Current Version
// =============================================================================
function smartops_demo_current_version(
    string $host,
    string $database,
    string $user,
    string $password,
    string $charset
): ?string {
    try {
        $dsn = "mysql:host={$host};dbname={$database};charset={$charset}";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        $statement = $pdo->query(
            "SELECT meta_value FROM demo_meta WHERE meta_key='dataset_version' LIMIT 1"
        );
        $value = $statement ? $statement->fetchColumn() : false;
        return $value === false ? null : (string)$value;
    } catch (Throwable $exception) {
        return null;
    }
}


// =============================================================================
// SECTION: SmartOps Render Database Setup Error
// =============================================================================
function smartops_render_database_setup_error(Throwable $exception): never {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SmartOps Database Setup</title>
  <style>
    body{margin:0;background:#f4f8fc;font-family:Arial,sans-serif;color:#172033;display:grid;place-items:center;min-height:100vh}
    .card{width:min(680px,calc(100% - 32px));background:#fff;border:1px solid #dbe6f1;border-radius:18px;padding:28px;box-shadow:0 18px 50px rgba(22,55,92,.12)}
    h1{margin:0 0 10px;font-size:26px}p{line-height:1.6}.error{background:#fff2f2;border:1px solid #ffcaca;border-radius:12px;padding:12px 14px;color:#9d1c1c}
    code{background:#edf3f8;padding:2px 6px;border-radius:5px}.steps{margin-top:18px;padding-left:20px;line-height:1.8}
  </style>
</head>
<body>
  <main class="card">
    <h1>SmartOps could not initialise MySQL</h1>
    <p class="error">{$message}</p>
    <ol class="steps">
      <li>Start <strong>Apache</strong> and <strong>MySQL</strong> in XAMPP.</li>
      <li>Confirm the default local account is <code>root</code> with an empty password, or update <code>config/db.php</code>.</li>
      <li>Reload this page. The bundled latest SQL snapshot will be installed automatically.</li>
    </ol>
  </main>
</body>
</html>
HTML;
    exit;
}


// =============================================================================
// SECTION: SmartOps Ensure Demo Database
// =============================================================================
function smartops_ensure_demo_database(
    string $host,
    string $database,
    string $user,
    string $password,
    string $charset
): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $autoInstall = getenv('SMARTOPS_AUTO_INSTALL');
    if ($autoInstall !== false && in_array(strtolower(trim($autoInstall)), ['0', 'false', 'off', 'no'], true)) {
        return;
    }

    try {
        $expectedVersion = smartops_expected_snapshot_version();
        $currentVersion = smartops_demo_current_version($host, $database, $user, $password, $charset);
        if ($currentVersion === $expectedVersion) {
            return;
        }

        $sqlFile = dirname(__DIR__) . '/phpmyadmin_database/smartops_unified_database.sql';
        smartops_demo_import_sql($host, $user, $password, $sqlFile);

        $installedVersion = smartops_demo_current_version($host, $database, $user, $password, $charset);
        if ($installedVersion !== $expectedVersion) {
            throw new RuntimeException(
                'Database import completed, but the installed snapshot version does not match the packaged version.'
            );
        }
    } catch (Throwable $exception) {
        smartops_render_database_setup_error($exception);
    }
}
