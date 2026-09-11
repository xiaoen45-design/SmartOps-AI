<?php
/**
 * SmartOps Source: technician_website/login.php
 * Purpose: Technician mobile login page inside the phone preview frame.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/../includes/technician_layout.php';

$error = '';
$success = trim((string)($_GET['reset'] ?? '')) === '1'
    ? 'Password updated. Please login with your new password.'
    : '';
if (trim((string)($_GET['access'] ?? '')) === 'removed') {
    $error = 'This technician account is no longer active. Please contact the administrator.';
}


// =============================================================================
// SECTION: Form Processing and Validation
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $technicianIdInput = strtoupper(trim((string)($_POST['technician_id'] ?? '')));
    if (preg_match('/^(?:TECH-?)?(\d{1,3})$/', $technicianIdInput, $idMatch)) {
        $technicianId = 'TECH-' . str_pad($idMatch[1], 3, '0', STR_PAD_LEFT);
    } else {
        $technicianId = $technicianIdInput;
    }
    $password = trim((string)($_POST['password'] ?? ''));
    try {

        // =============================================================================
        // SECTION: Database Queries and Data Preparation
        // =============================================================================
        $technician = fetch_one(
            'SELECT * FROM technicians WHERE technician_id = ? AND is_active=1',
            [$technicianId]
        );
        $storedPassword = (string)($technician['password'] ?? '');
        $passwordInfo = password_get_info($storedPassword);
        $isHash = $storedPassword !== '' && (($passwordInfo['algoName'] ?? 'unknown') !== 'unknown');
        $validPassword = $technician && ($isHash
            ? password_verify($password, $storedPassword)
            : hash_equals($storedPassword, $password));

        if ($validPassword) {
            if (!$isHash || password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                execute_sql(
                    'UPDATE technicians SET password=? WHERE technician_id=?',
                    [password_hash($password, PASSWORD_DEFAULT), $technicianId]
                );
            }
            session_regenerate_id(true);
            $_SESSION['technician_id'] = $technician['technician_id'];
            redirect_to('home.php');
        }

        $error = 'Invalid technician ID or password.';
    } catch (Throwable $exception) {
        $error = 'Database is unavailable. Start MySQL and import the SmartOps SQL file before signing in.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Technician Login</title>
  <link rel="stylesheet" href="<?= e(technician_asset_url('css')) ?>?v=<?= e(technician_asset_version('css')) ?>">
</head>
<body class="tech-body tech-auth-body">
  <div class="device-stage">
    <div class="phone-frame" aria-label="Mobile website preview frame">
      <span class="phone-side-button phone-side-button--volume" aria-hidden="true"></span>
      <span class="phone-side-button phone-side-button--power" aria-hidden="true"></span>
      <span class="phone-top-cutout" aria-hidden="true"></span>
      <div class="phone-screen">
        <main class="phone-shell auth-phone-shell">
          <div class="center-card card">
    <div class="brand">
      <span>Smart</span><strong>Ops</strong>
      <small>Technician Website</small>
    </div>

    <h1 class="mt">Technician Login</h1>

    <?php if ($success !== ''): ?><div class="success mt"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="error mt"><?= e($error) ?></div><?php endif; ?>

    <!-- SECTION: Form -->
    <form class="tech-form mt" method="post">
      <label for="technicianId">Technician ID</label>
      <input class="form-control" id="technicianId" name="technician_id" placeholder="Technician ID" required>

      <label for="technicianPassword">Password</label>
      <input class="form-control" id="technicianPassword" name="password" type="password" placeholder="Password" required>

      <div class="forgot-row"><a href="forgot_password.php">Forgot Password?</a></div>
      <button class="btn" type="submit">Login</button>
    </form>
          </div>
        </main>
      </div>
    </div>
  </div>
  <script src="<?= e(technician_asset_url('js')) ?>?v=<?= e(technician_asset_version('js')) ?>"></script>
</body>
</html>
