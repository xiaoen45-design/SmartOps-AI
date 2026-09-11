<?php
/**
 * SmartOps Source: admin_portal/hitl/daily_component_cases.php
 * Purpose: Admin Portal / HITL: Daily Component Cases server-side page, endpoint, or reusable module.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../admin_auth.php';
require_once __DIR__ . '/daily_summary_helpers.php';

// =============================================================================
// SECTION: Authentication and Access Control
// =============================================================================
require_admin();

$date = hitl_daily_valid_date((string)($_GET['date'] ?? ''));
$queue = hitl_daily_valid_queue((string)($_GET['queue'] ?? 'All'));
$asset = trim((string)($_GET['asset'] ?? ''));
$component = trim((string)($_GET['component'] ?? ''));
if ($asset === '' || $component === '') redirect_to(app_url('admin_portal/hitl/dashboard.php'));

$rows = hitl_daily_fetch_rows($date, $queue, $asset, $component);
$technicians = fetch_assignable_technicians('Technical Specialist');

// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
admin_ui_header(
    component_display_name($component, $asset) . ' HITL Case Records',
    'hitl',
    date('d M Y', strtotime($date)) . ' · ' . hitl_daily_queue_label($queue) . ' · ' . asset_display_name($asset),
    true
);
?>
<!-- SECTION: Data Table -->
<section class="card pending-review-table-card">
  <div class="table-header"><div><h2>Daily HITL Cases</h2><p><?= e(number_format(count($rows))) ?> case(s) for the selected component.</p></div></div>
  <div class="table-box pending-review-table-box">
    <!-- SECTION: Data Table -->
    <table class="dashboard-table daily-case-table">
      <thead><tr><th>Case ID</th><th>Room</th><th>Category</th><th>Component</th><th>Severity</th><th>Priority</th><th>HITL Queue</th><th>Review Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="9" class="empty-table-message">No cases were found for this component.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row):
          $caseId = (string)($row['case_id'] ?? '');
          $trigger = hitl_primary_escalation_trigger($row);
          $detail = [
              'caseId' => $caseId,
              'room' => (string)($row['room'] ?? '-'),
              'asset' => asset_display_name($row['hotel_asset'] ?? 'Other'),
              'assetRaw' => (string)($row['hotel_asset'] ?? ''),
              'component' => (string)($row['component'] ?? '-'),
              'issue' => (string)($row['issue'] ?: $row['issue_summary'] ?: '-'),
              'failureMode' => failure_mode_display_name($row['failure_mode'] ?? '-', $row['component'] ?? '', $row['hotel_asset'] ?? ''),
              'safety' => (string)($row['safety_flag'] ?? '-'),
              'severity' => (string)($row['severity'] ?? '-'),
              'priority' => priority_display_name($row['priority'] ?? ''),
              'trigger' => $trigger,
              'reason' => (string)($row['hitl_reason'] ?? $trigger),
              'confidence' => (string)($row['ai_confidence'] ?? '-'),
              'isRestricted' => false,
              'isOokb' => hitl_is_out_of_knowledge_case($row),
          ];
        ?>
          <tr>
            <td><?= e($caseId) ?></td>
            <td><?= e($row['room'] ?: '-') ?></td>
            <td><?= e(asset_display_name($row['hotel_asset'] ?? 'Other')) ?></td>
            <td><?= e(component_display_name((string)($row['component'] ?? '-'), $asset)) ?></td>
            <td><?= severity_badge_html($row['severity'] ?? '') ?></td>
            <td><?= e(priority_display_name($row['priority'] ?? '')) ?></td>
            <td><?= e(hitl_daily_queue_label($trigger)) ?></td>
            <td><?= e($row['review_status'] ?: 'Pending Review') ?></td>
            <td><button type="button" class="review-btn" data-review='<?= e(json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>View Details</button></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include __DIR__ . '/review_modal.php'; ?>
<script>window.hitlTechnicians = <?= json_encode($technicians, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<?php admin_ui_footer(); ?>
