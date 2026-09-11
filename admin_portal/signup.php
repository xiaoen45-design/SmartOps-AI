<?php
/**
 * SmartOps Source: admin_portal/signup.php
 * Purpose: Admin Portal: Signup server-side page, endpoint, or reusable module.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/admin_auth.php';

$message = '';
$messageType = 'error-message';


// =============================================================================
// SECTION: Form Processing and Validation
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $accessCode = trim((string)($_POST['access_code'] ?? ''));

    $configuredAccessCode = admin_access_code();
    if ($configuredAccessCode === '') {
        $message = 'Admin account changes are disabled until a local access code is configured.';
    } elseif (!hash_equals($configuredAccessCode, $accessCode)) {
        $message = 'Invalid admin access code.';
    } elseif ($password !== $confirmPassword) {
        $message = 'Password and confirm password do not match.';
    } elseif (!db_table_exists('admin_users')) {
        $message = 'Please import the SQL file first.';
    } else {

        // =============================================================================
        // SECTION: Database Queries and Data Preparation
        // =============================================================================
        execute_sql(
            'INSERT INTO admin_users (full_name, email, password_hash, role, created_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                password_hash = VALUES(password_hash)',
            [$fullName, $email, password_hash($password, PASSWORD_DEFAULT), 'System Administrator']
        );
        $message = 'Admin account created. You can login now.';
        $messageType = 'success-message';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartOps Admin Sign Up</title>
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

        <h2 class="login-title">Admin Sign Up</h2>
        <p class="subtitle">Create a new SmartOps admin account</p>

        <?php if ($message !== ''): ?>
          <p class="form-message <?= e($messageType) ?>"><?= e($message) ?></p>
        <?php endif; ?>

        <!-- SECTION: Form -->
        <form action="signup.php" method="post">
          <div class="form-group"><label for="fullName">Full Name</label><input id="fullName" type="text" name="full_name" placeholder="Enter full name" required></div>
          <div class="form-group"><label for="signupEmail">Email</label><input id="signupEmail" type="email" name="email" placeholder="Enter email address" required></div>
          <div class="form-group"><label for="signupPassword">Password</label><input id="signupPassword" type="password" name="password" placeholder="Enter password" required></div>
          <div class="form-group"><label for="confirmPassword">Confirm Password</label><input id="confirmPassword" type="password" name="confirm_password" placeholder="Confirm password" required></div>
          <div class="form-group"><label for="accessCode">Admin Access Code</label><input id="accessCode" type="password" name="access_code" placeholder="Enter access code" required></div>
          <button type="submit" class="login-btn">Sign Up</button>
        </form>

        <div class="options"><a href="login.php" class="signup-link">Back to Login</a></div>
      </div>
    </div>
  </div>
  <script src="<?= e(app_url('assets/js/admin/auth.js')) ?>?v=<?= e(asset_file_version('assets/js/admin/auth.js')) ?>"></script>
</body>
</html>
