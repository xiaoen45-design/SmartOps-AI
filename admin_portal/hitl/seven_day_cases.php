<?php
/**
 * SmartOps Source: admin_portal/hitl/seven_day_cases.php
 * Purpose: Admin Portal / HITL: Seven Day Cases server-side page, endpoint, or reusable module.
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

$queue = hitl_daily_valid_queue((string)($_GET['queue'] ?? 'All'));
$asset = trim((string)($_GET['asset'] ?? ''));
$component = trim((string)($_GET['component'] ?? ''));
$rows = hitl_seven_day_fetch_rows($queue, $asset, $component);
$windowLabel = hitl_seven_day_window_label();
$queueLabel = hitl_daily_queue_label($queue);

$titlePart = $component !== '' ? component_display_name($component, $asset) : ($asset !== '' ? asset_display_name($asset) : $queueLabel);

// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
admin_ui_header(
    $titlePart . ' 7-Day HITL Cases',
    'hitl',
    'Cases that entered HITL from ' . $windowLabel . '.',
    true
);
?>
<!-- SECTION: KPI Cards -->
<section class="kpis four detail-summary-kpis daily-hitl-kpis">
  <div class="kpi"><h3>Reporting Window</h3><h2 class="text-kpi"><?= e($windowLabel) ?></h2></div>
  <div class="kpi"><h3>Total Cases</h3><h2><?= e(number_format(count($rows))) ?></h2></div>
  <div class="kpi"><h3>Selected Queue</h3><h2 class="text-kpi"><?= e($queueLabel) ?></h2></div>
  <div class="kpi"><h3>Selected Category</h3><h2 class="text-kpi"><?= e($asset !== '' ? asset_display_name($asset) : 'All') ?></h2></div>
</section>

<!-- SECTION: Data Table -->
<section class="card pending-review-table-card">
  <div class="table-header"><div><h2>7-Day HITL Case Records</h2><p>Historical records remain visible even after manager approval or dispatch.</p></div></div>
  <div class="table-box pending-review-table-box">
    <!-- SECTION: Data Table -->
    <table class="dashboard-table">
      <thead><tr><th>Case ID</th><th>Date</th><th>Room</th><th>Category</th><th>Component</th><th>Severity</th><th>Priority</th><th>HITL Queue</th><th>Review Status</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="9" class="empty-table-message">No HITL cases were found for this seven-day selection.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row):
          $eventTimestamp = (int)($row['_seven_day_ts'] ?? 0);
          $trigger = hitl_primary_escalation_trigger($row);
        ?>
          <tr>
            <td><strong><?= e((string)($row['case_id'] ?? '-')) ?></strong></td>
            <td><?= e($eventTimestamp ? date('d M Y', $eventTimestamp) : '-') ?></td>
            <td><?= e((string)($row['room'] ?: '-')) ?></td>
            <td><?= e(asset_display_name($row['hotel_asset'] ?? 'Other')) ?></td>
            <td><?= e(component_display_name((string)($row['component'] ?: 'Unknown'), (string)($row['hotel_asset'] ?? ''))) ?></td>
            <td><?= severity_badge_html($row['severity'] ?? '') ?></td>
            <td><?= e(priority_display_name($row['priority'] ?? '')) ?></td>
            <td><?= e(hitl_daily_queue_label($trigger)) ?></td>
            <td><?= e((string)($row['review_status'] ?: 'Pending Review')) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_ui_footer(); ?>
