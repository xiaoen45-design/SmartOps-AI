<?php
/**
 * SmartOps Source: admin_portal/settings.php
 * Purpose: Admin Portal: Settings server-side page, endpoint, or reusable module.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/admin_auth.php';

// =============================================================================
// SECTION: Authentication and Access Control
// =============================================================================
require_admin();

$returnPage = (string)($_GET['return'] ?? 'overview/dashboard.php');
if (
  $returnPage === ''
  || str_contains($returnPage, '..')
  || str_contains($returnPage, '://')
  || str_starts_with($returnPage, '/')
  || !preg_match('/^[A-Za-z0-9_\/?.=&%+-]+$/', $returnPage)
) {
  $returnPage = 'overview/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Settings</title>
  <link rel="stylesheet" href="<?= e(app_url('assets/css/admin/settings.css')) ?>?v=<?= e(asset_file_version('assets/css/admin/settings.css')) ?>">
</head>
<body>
  <div class="settings-page">
    <div class="settings-card">
      <div class="profile-avatar">A</div>
      <h1>Administrator Profile</h1>
      <p class="subtitle">Manage administrator account information</p>

      <div class="profile-info">
        <div><label for="adminName">Administrator Name</label><input type="text" id="adminName" value="<?= e(current_admin_name()) ?>" disabled></div>
        <div><label for="adminEmail">Email Address</label><input type="email" id="adminEmail" value="<?= e(current_admin_email()) ?>" disabled></div>
        <div><label for="adminRole">Role</label><input type="text" id="adminRole" value="System Administrator" disabled></div>
        <div><label for="adminDepartment">Department</label><input type="text" id="adminDepartment" value="Hotel Operations" disabled></div>
        <div><label for="lastLogin">Last Login</label><input type="text" id="lastLogin" value="<?= e(date('d M Y • h:i A')) ?>" disabled></div>
      </div>

      <div class="button-row">
        <button type="button" id="editBtn" data-settings-action="edit">Edit Profile</button>
        <button type="button" class="secondary-btn" data-settings-action="open-password">Reset Password</button>
      </div>

      <div class="button-row hidden" id="saveRow">
        <button type="button" data-settings-action="save">Save Changes</button>
        <button type="button" class="secondary-btn" data-settings-action="cancel-edit">Cancel</button>
      </div>

      <a href="<?= e($returnPage) ?>" id="backToDashboard" class="back-link">← Back to Dashboard</a>
    </div>
  </div>

  <div class="modal" id="passwordModal" aria-hidden="true">
    <div class="modal-content">
      <h2>Reset Password</h2>
      <label for="currentPassword">Current Password</label><input type="password" id="currentPassword">
      <label for="newPassword">New Password</label><input type="password" id="newPassword">
      <label for="confirmPassword">Confirm Password</label><input type="password" id="confirmPassword">
      <p id="passwordMessage"></p>
      <div class="button-row">
        <button type="button" data-settings-action="update-password">Update Password</button>
        <button type="button" class="secondary-btn" data-settings-action="close-password">Cancel</button>
      </div>
    </div>
  </div>

  <script src="<?= e(app_url('assets/js/admin/settings.js')) ?>?v=<?= e(asset_file_version('assets/js/admin/settings.js')) ?>"></script>
</body>
</html>
