<?php
/**
 * SmartOps Source: admin_portal/forgot_password.php
 * Purpose: Admin Portal: Forgot Password server-side page, endpoint, or reusable module.
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
    $email = trim((string)($_POST['email'] ?? ''));
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $accessCode = trim((string)($_POST['access_code'] ?? ''));

    $configuredAccessCode = admin_access_code();
    if ($configuredAccessCode === '') {
        $message = 'Admin account changes are disabled until a local access code is configured.';
    } elseif (!hash_equals($configuredAccessCode, $accessCode)) {
        $message = 'Invalid admin access code.';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'New password and confirm password do not match.';
    } elseif (!db_table_exists('admin_users')) {
        $message = 'Please import the SQL file first.';
    } else {

        // =============================================================================
        // SECTION: Database Queries and Data Preparation
        // =============================================================================
        execute_sql(
            'UPDATE admin_users SET password_hash = ? WHERE email = ?',
            [password_hash($newPassword, PASSWORD_DEFAULT), $email]
        );
        $message = 'Password reset completed. You can login now.';
        $messageType = 'success-message';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartOps Forgot Password</title>
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

        <h2 class="login-title">Reset Password</h2>
        <p class="subtitle">Reset your admin password</p>

        <?php if ($message !== ''): ?>
          <p class="form-message <?= e($messageType) ?>"><?= e($message) ?></p>
        <?php endif; ?>

        <!-- SECTION: Form -->
        <form action="forgot_password.php" method="post">
          <div class="form-group"><label for="resetEmail">Email</label><input id="resetEmail" type="email" name="email" placeholder="Enter email address" required></div>
          <div class="form-group"><label for="newPassword">New Password</label><input id="newPassword" type="password" name="new_password" placeholder="Enter new password" required></div>
          <div class="form-group"><label for="confirmNewPassword">Confirm New Password</label><input id="confirmNewPassword" type="password" name="confirm_password" placeholder="Confirm new password" required></div>
          <div class="form-group"><label for="resetAccessCode">Admin Access Code</label><input id="resetAccessCode" type="password" name="access_code" placeholder="Enter access code" required></div>
          <button type="submit" class="login-btn">Reset Password</button>
        </form>

        <div class="options"><a href="login.php" class="signup-link">Back to Login</a></div>
      </div>
    </div>
  </div>
  <script src="<?= e(app_url('assets/js/admin/auth.js')) ?>?v=<?= e(asset_file_version('assets/js/admin/auth.js')) ?>"></script>
</body>
</html>
