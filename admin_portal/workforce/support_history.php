<?php
/**
 * SmartOps Source: admin_portal/workforce/support_history.php
 * Purpose: Admin Portal / Workforce: Support History server-side page, endpoint, or reusable module.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../admin_auth.php';

// =============================================================================
// SECTION: Authentication and Access Control
// =============================================================================
require_admin();
ensure_workflow_support_columns();
refresh_live_sla_statuses();
$rollingWindow = rolling_seven_day_window();
$rollingStart = $rollingWindow['start'];
$rollingEndExclusive = $rollingWindow['end_exclusive'];
$rollingWindowLabel = $rollingWindow['label'];

$reason = trim((string)($_GET['reason'] ?? 'All'));
$allowed = ['All', 'Outsourcing', 'Senior Support', 'Parts Required'];
if (!in_array($reason, $allowed, true)) $reason = 'All';

$where = "support_reason IN ('Outsourcing','Senior Support','Parts Required')
          AND support_requested_at IS NOT NULL
          AND support_requested_at >= ?
          AND support_requested_at < ?";
$params = [$rollingStart, $rollingEndExclusive];
if ($reason !== 'All') {
    $where .= ' AND support_reason=?';
    $params[] = $reason;
}


// =============================================================================
// SECTION: Database Queries and Data Preparation
// =============================================================================
$rows = fetch_all(
    "SELECT * FROM workforce_cases
     WHERE {$where}
     ORDER BY support_requested_at DESC, case_id DESC",
    $params
);

$pending = 0;
$processed = 0;
$reasonCounts = ['Outsourcing' => 0, 'Senior Support' => 0, 'Parts Required' => 0];
foreach ($rows as $row) {
    $supportReason = trim((string)($row['support_reason'] ?? ''));
    if (isset($reasonCounts[$supportReason])) $reasonCounts[$supportReason]++;
    if (workforce_case_needs_manager_action($row)) $pending++;
    else $processed++;
}


// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
admin_ui_header(
    $reason === 'All' ? '7-Day Support Request History' : '7-Day ' . $reason . ' History',
    'workforce',
    'Review technician support requests submitted during ' . $rollingWindowLabel . '.',
    true
);
?>
<!-- SECTION: KPI Cards -->
<section class="kpis one-row four workforce-detail-kpis support-history-kpis">
  <div class="kpi"><h3>7-Day Requests</h3><h2><?= e(number_format(count($rows))) ?></h2></div>
  <div class="kpi"><h3>Pending Now</h3><h2><?= e(number_format($pending)) ?></h2></div>
  <div class="kpi"><h3>Processed</h3><h2><?= e(number_format($processed)) ?></h2></div>
  <div class="kpi"><h3>Review Scope</h3><h2 class="text-value"><?= e($reason === 'All' ? 'All Support Types' : $reason) ?></h2></div>
</section>

<!-- SECTION: Filters / Controls -->
<section class="card support-history-filter-card">
  <div class="support-history-filter-row" aria-label="Support history filters">
    <?php foreach ($allowed as $filter): ?>
      <a class="support-history-filter<?= $filter === $reason ? ' active' : '' ?>" href="<?= e(app_url('admin_portal/workforce/support_history.php?reason=' . rawurlencode($filter))) ?>">
        <span><?= e($filter === 'All' ? 'All Requests' : $filter) ?></span>
        <?php if ($filter !== 'All'): ?><strong><?= e(number_format($reasonCounts[$filter] ?? 0)) ?></strong><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- SECTION: Data Table -->
<section class="card detail-table-card support-history-table-card">
  <div class="table-header">
    <div>
      <h2><?= e($reason === 'All' ? 'All Support Requests' : $reason . ' Requests') ?></h2>
      <p>Historical requests remain visible after manager action so support demand can be reviewed over time.</p>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Requested At</th>
          <th>Case ID</th>
          <th>Technician</th>
          <th>Department</th>
          <th>Support Needed</th>
          <th>Issue / Detail</th>
          <th>Current Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="empty-table-message">No support requests were recorded in the latest seven days.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $row):
          $isPending = workforce_case_needs_manager_action($row);
          $supportReason = trim((string)($row['support_reason'] ?? ''));
          $detail = trim((string)($row['technician_note'] ?? ''));
          if ($detail === '') {
              if ($supportReason === 'Outsourcing') $detail = trim((string)($row['outsourcing_reason'] ?? $row['required_external_service'] ?? ''));
              elseif ($supportReason === 'Parts Required') $detail = trim((string)($row['requested_part'] ?? ''));
              else $detail = trim((string)($row['issue'] ?? $row['issue_summary'] ?? ''));
          }
          $status = trim((string)($row['support_status'] ?? ''));
          if ($status === '') $status = $isPending ? 'Pending Manager Action' : 'Processed';
      ?>
        <tr>
          <td><?= e($row['support_requested_at'] ? date('d M Y, H:i', strtotime((string)$row['support_requested_at'])) : '-') ?></td>
          <td><strong><?= e($row['case_id'] ?? '') ?></strong></td>
          <td><?= e($row['previous_technician_id'] ?: $row['assigned_technician_id'] ?: 'Unassigned') ?></td>
          <td><?= e(asset_display_name($row['hotel_asset'] ?? 'Unknown')) ?></td>
          <td><span class="support-history-reason reason-<?= e(strtolower(str_replace(' ', '-', $supportReason))) ?>"><?= e($supportReason ?: '-') ?></span></td>
          <td><?= e($detail ?: '-') ?></td>
          <td><span class="support-history-status<?= $isPending ? ' pending' : ' processed' ?>"><?= e($status) ?></span></td>
          <td>
            <?php if ($isPending): ?>
              <a class="review-btn" href="<?= e(app_url('admin_portal/workforce/action_support.php?reason=' . rawurlencode($supportReason) . '&source=workforce')) ?>">Review</a>
            <?php else: ?>
              <span class="support-history-done">Recorded</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_ui_footer(); ?>
