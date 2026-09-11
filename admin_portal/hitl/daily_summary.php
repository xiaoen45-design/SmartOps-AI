<?php
/**
 * SmartOps Source: admin_portal/hitl/daily_summary.php
 * Purpose: Admin Portal / HITL: Daily Summary server-side page, endpoint, or reusable module.
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
$queueLabel = hitl_daily_queue_label($queue);
$rows = hitl_daily_fetch_rows($date, $queue);

$summary = [];
foreach ($rows as $row) {
    $asset = asset_display_name($row['hotel_asset'] ?? 'Other');
    if (!isset($summary[$asset])) {
        $summary[$asset] = ['rows' => []];
    }
    $summary[$asset]['rows'][] = $row;
}
foreach ($summary as $asset => &$entry) {
    $entry = array_merge($entry, hitl_daily_breakdown($entry['rows']));
}
unset($entry);
uasort($summary, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);


// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
admin_ui_header(
    $queueLabel . ' Daily HITL Summary',
    'hitl',
    'Category-level escalation summary for ' . date('d M Y', strtotime($date)) . '.',
    true
);
?>
<!-- SECTION: KPI Cards -->
<section class="kpis four detail-summary-kpis daily-hitl-kpis">
  <div class="kpi"><h3>Selected Date</h3><h2 class="text-kpi"><?= e(date('d M Y', strtotime($date))) ?></h2></div>
  <div class="kpi"><h3>Total HITL Cases</h3><h2><?= e(number_format(count($rows))) ?></h2></div>
  <div class="kpi"><h3>Maintenance Categories</h3><h2><?= e(number_format(count($summary))) ?></h2></div>
  <div class="kpi"><h3>Selected Queue</h3><h2 class="text-kpi"><?= e($queueLabel) ?></h2></div>
</section>

<!-- SECTION: Content Card -->
<section class="card daily-hitl-summary-card">
  <div class="table-header">
    <div>
      <h2>Maintenance Category Summary</h2>
      <p>Select a category to view its component-level HITL breakdown.</p>
    </div>
  </div>
  <div class="table-box">
    <!-- SECTION: Data Table -->
    <table class="dashboard-table component-summary-table daily-summary-table">
      <thead><tr><th>Category</th><th>Total Cases</th><th>Immediate</th><th>Urgent</th><th>Low Confidence</th><th>Out of KB</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (!$summary): ?>
        <tr><td colspan="7" class="empty-table-message">No HITL cases were recorded for this date and queue.</td></tr>
      <?php else: ?>
        <?php foreach ($summary as $asset => $values): ?>
          <tr>
            <td><strong><?= e($asset) ?></strong></td>
            <td><?= e(number_format($values['total'])) ?></td>
            <td><?= e(number_format($values['immediate'])) ?></td>
            <td><?= e(number_format($values['urgent'])) ?></td>
            <td><?= e(number_format($values['low_confidence'])) ?></td>
            <td><?= e(number_format($values['out_of_kb'])) ?></td>
            <td><a class="review-btn" href="<?= e(app_url('admin_portal/hitl/daily_category_summary.php?date=' . rawurlencode($date) . '&queue=' . rawurlencode($queue) . '&asset=' . rawurlencode($asset))) ?>">View Summary</a></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_ui_footer(); ?>
