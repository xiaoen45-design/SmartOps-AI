<?php
/**
 * SmartOps Source: admin_portal/login.php
 * Purpose: Admin authentication page and login validation.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/admin_auth.php';

$error = '';


// =============================================================================
// SECTION: Form Processing and Validation
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $authenticated = false;
    $adminName = 'SmartOps Admin';

    try {
        if (db_table_exists('admin_users')) {

            // =============================================================================
            // SECTION: Database Queries and Data Preparation
            // =============================================================================
            $admin = fetch_one('SELECT * FROM admin_users WHERE email = ? LIMIT 1', [$email]);
            if ($admin && password_verify($password, (string)$admin['password_hash'])) {
                $authenticated = true;
                $adminName = (string)($admin['full_name'] ?? 'SmartOps Admin');
            }
        }
    } catch (Throwable $exception) {
        $authenticated = false;
    }

    if ($authenticated) {
        session_regenerate_id(true);
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_name'] = $adminName;
        redirect_to('overview/dashboard.php');
    }

    $error = 'Incorrect email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartOps Admin Portal</title>
  <link rel="stylesheet" href="<?= e(app_url('assets/css/admin/auth.css')) ?>?v=<?= e(asset_file_version('assets/css/admin/auth.css')) ?>">
</head>
<body>
  <div class="container">
    <div class="right-panel">
      <div class="login-box">
        <div class="logo">
          <h1><span class="smart">Smart</span><span class="stay">Ops</span></h1>
          <p>Admin Portal</p>
        </div>

        <h2 class="login-title">Admin Login</h2>
        <p class="subtitle">Sign in to access the SmartOps admin dashboard</p>

        <?php if ($error !== ''): ?>
          <p class="form-message error-message"><?= e($error) ?></p>
        <?php endif; ?>

        <!-- SECTION: Form -->
        <form action="login.php" method="post">
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Enter your email address" required>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
          </div>

          <div class="options">
            <a href="signup.php" class="signup-link">Sign Up</a>
            <a href="forgot_password.php" class="forgot">Forgot Password?</a>
          </div>

          <button type="submit" class="login-btn">Login</button>
        </form>

        <div class="footer">© 2026 SmartOps. All rights reserved.</div>
      </div>
    </div>
  </div>
  <script src="<?= e(app_url('assets/js/admin/auth.js')) ?>?v=<?= e(asset_file_version('assets/js/admin/auth.js')) ?>"></script>
</body>
</html>
