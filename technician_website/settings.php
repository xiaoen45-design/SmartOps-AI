<?php
/**
 * SmartOps Source: technician_website/settings.php
 * Purpose: Technician profile, contact information, and password settings.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/auth.php';

// =============================================================================
// SECTION: Authentication and Access Control
// =============================================================================
$techId = require_technician();
refresh_live_sla_statuses();
refresh_technician_summary($techId);
$tech = current_technician();

$contactSuccess = '';
$passwordSuccess = '';
$contactError = '';
$passwordError = '';


// =============================================================================
// SECTION: Form Processing and Validation
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'contact') {
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($phone === '' || $email === '') {
            $contactError = 'Please enter both phone number and email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $contactError = 'Please enter a valid email address.';
        } else {

            // =============================================================================
            // SECTION: Database Queries and Data Preparation
            // =============================================================================
            execute_sql(
                "UPDATE technicians SET phone=?, email=? WHERE technician_id=?",
                [$phone, $email, $techId]
            );
            $contactSuccess = 'Contact information updated successfully.';
            $tech = current_technician();
        }
    }

    if ($formType === 'password') {
        $current = trim($_POST['current_password'] ?? '');
        $new = trim($_POST['new_password'] ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');
        $stored = (string)($tech['password'] ?? '');

        if ($current === '' || $new === '' || $confirm === '') {
            $passwordError = 'Please complete all password fields.';
        } elseif (!(password_get_info($stored)['algoName'] !== 'unknown' ? password_verify($current, $stored) : hash_equals($stored, $current))) {
            $passwordError = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $passwordError = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $passwordError = 'New password and confirmation do not match.';
        } else {
            execute_sql(
                "UPDATE technicians SET password=? WHERE technician_id=?",
                [password_hash($new, PASSWORD_DEFAULT), $techId]
            );
            $passwordSuccess = 'Password updated successfully.';
            $tech = current_technician();
        }
    }
}

$assigned = scalar("SELECT COUNT(*) FROM technician_tasks WHERE technician_id=? AND task_status IN ('Assigned','Waiting Technician Acceptance','Accepted')", [$techId]);
$progress = scalar("SELECT COUNT(*) FROM technician_tasks WHERE technician_id=? AND task_status IN ('In Progress','Repair In Progress','Started')", [$techId]);
$completed = db_table_exists('task_assignment_history')
    ? scalar("SELECT COUNT(*) FROM task_assignment_history WHERE technician_id=? AND end_status='Repair Completed'", [$techId])
    : scalar("SELECT COUNT(*) FROM technician_tasks WHERE technician_id=? AND task_status='Completed'", [$techId]);
$activeTasks = technician_active_task_count($techId);
$storedStatus = trim((string)($tech['status'] ?? ''));
$displayStatus = str_contains(strtolower($storedStatus), 'leave') ? 'On Leave' : ($activeTasks > 0 ? 'Busy' : 'Available');
$statusClass = match (strtolower($displayStatus)) {
    'available' => 'is-available',
    'busy' => 'is-busy',
    default => 'is-away',
};

$name = trim((string)($tech['name'] ?? $techId));
$nameParts = preg_split('/\s+/', $name) ?: [];
$initials = '';
foreach (array_slice($nameParts, 0, 2) as $part) {
    if ($part !== '') $initials .= strtoupper(substr($part, 0, 1));
}
if ($initials === '') $initials = 'T';

$contactOpen = $contactSuccess !== '' || $contactError !== '';
$passwordOpen = $passwordSuccess !== '' || $passwordError !== '';


// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
tech_header('Account', 'account');
?>
<div class="account-page-stack">
  <!-- SECTION: Profile Summary -->
  <section class="card account-profile-card" id="technicianProfile">
    <div class="profile-identity-block">
      <div class="profile-avatar" aria-hidden="true"><?= e($initials) ?></div>
      <div class="profile-identity-copy">
        <h2><?= e($name) ?></h2>
        <p><?= e($techId) ?></p>
      </div>
      <span class="profile-status-badge <?= e($statusClass) ?>"><?= e($displayStatus) ?></span>
    </div>

    <dl class="profile-detail-list account-profile-details">
      <div class="profile-detail-row"><dt>Role</dt><dd><?= e(technician_workflow_role_label($tech)) ?></dd></div>
      <div class="profile-detail-row"><dt>Department</dt><dd><?= e(technician_department_label($tech)) ?></dd></div>
      <div class="profile-detail-row"><dt>Level</dt><dd><?= e(technician_level_label($tech)) ?></dd></div>
    </dl>

    <div class="account-work-summary" aria-label="Current work summary">
      <div><span>Assigned</span><strong><?= e($assigned) ?></strong></div>
      <div><span>In Progress</span><strong><?= e($progress) ?></strong></div>
      <div><span>Completed</span><strong><?= e($completed) ?></strong></div>
    </div>
  </section>

  <details class="card account-accordion"<?= $contactOpen ? ' open' : '' ?>>
    <summary>
      <span><strong>Contact Information</strong><small>Phone number and email address</small></span>
      <i aria-hidden="true"></i>
    </summary>
    <div class="account-accordion-content">
      <?php if ($contactSuccess): ?><div class="success"><?= e($contactSuccess) ?></div><?php endif; ?>
      <?php if ($contactError): ?><div class="error"><?= e($contactError) ?></div><?php endif; ?>

      <!-- SECTION: Account / Settings -->
      <form method="post" class="tech-form settings-form">
        <input type="hidden" name="form_type" value="contact">
        <label>
          <span>Phone Number</span>
          <input class="form-control" type="text" name="phone" value="<?= e($tech['phone'] ?? '') ?>" placeholder="Enter phone number" required>
        </label>
        <label>
          <span>Email Address</span>
          <input class="form-control" type="email" name="email" value="<?= e($tech['email'] ?? '') ?>" placeholder="Enter email address" required>
        </label>
        <button type="submit" class="btn">Save Changes</button>
      </form>
    </div>
  </details>

  <details class="card account-accordion"<?= $passwordOpen ? ' open' : '' ?>>
    <summary>
      <span><strong>Security</strong><small>Change your account password</small></span>
      <i aria-hidden="true"></i>
    </summary>
    <div class="account-accordion-content">
      <?php if ($passwordSuccess): ?><div class="success"><?= e($passwordSuccess) ?></div><?php endif; ?>
      <?php if ($passwordError): ?><div class="error"><?= e($passwordError) ?></div><?php endif; ?>

      <!-- SECTION: Account / Settings -->
      <form method="post" class="tech-form settings-form">
        <input type="hidden" name="form_type" value="password">
        <label><span>Current Password</span><input class="form-control" type="password" name="current_password" placeholder="Enter current password" required></label>
        <label><span>New Password</span><input class="form-control" type="password" name="new_password" placeholder="Enter new password" minlength="6" required></label>
        <label><span>Confirm New Password</span><input class="form-control" type="password" name="confirm_password" placeholder="Re-enter new password" minlength="6" required></label>
        <button type="submit" class="btn">Update Password</button>
      </form>
    </div>
  </details>

  <a class="btn btn-outline account-back-button" href="home.php?filter=assigned">Back to My Work</a>
</div>
<?php tech_footer(); ?>
