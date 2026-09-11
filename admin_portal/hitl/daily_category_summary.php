<?php
/**
 * SmartOps Source: admin_portal/hitl/daily_category_summary.php
 * Purpose: Admin Portal / HITL: Daily Category Summary server-side page, endpoint, or reusable module.
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
if ($asset === '') redirect_to(app_url('admin_portal/hitl/daily_summary.php?date=' . rawurlencode($date) . '&queue=' . rawurlencode($queue)));

$rows = hitl_daily_fetch_rows($date, $queue, $asset);
$summary = [];
foreach ($rows as $row) {
    $component = (string)($row['component'] ?: 'Unknown');
    if (!isset($summary[$component])) $summary[$component] = ['rows' => []];
    $summary[$component]['rows'][] = $row;
}
foreach ($summary as $component => &$entry) {
    $entry = array_merge($entry, hitl_daily_breakdown($entry['rows']));
}
unset($entry);
uasort($summary, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);


// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
admin_ui_header(
    asset_display_name($asset) . ' HITL Category Summary',
    'hitl',
    'Component-level summary for ' . date('d M Y', strtotime($date)) . ' · ' . hitl_daily_queue_label($queue) . '.',
    true
);
?>
<!-- SECTION: KPI Cards -->
<section class="kpis three detail-summary-kpis">
  <div class="kpi"><h3>Total HITL Cases</h3><h2><?= e(number_format(count($rows))) ?></h2></div>
  <div class="kpi"><h3>Components</h3><h2><?= e(number_format(count($summary))) ?></h2></div>
  <div class="kpi"><h3>Selected Category</h3><h2 class="text-kpi"><?= e(asset_display_name($asset)) ?></h2></div>
</section>
<!-- SECTION: Content Card -->
<section class="card">
  <div class="table-header"><div><h2>Component Categories</h2><p>Select a component to continue to its tailored HITL case records.</p></div></div>
  <div class="table-box">
    <!-- SECTION: Data Table -->
    <table class="dashboard-table component-summary-table daily-summary-table">
      <thead><tr><th>Component</th><th>Total Cases</th><th>Immediate</th><th>Urgent</th><th>Low Confidence</th><th>Out of KB</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (!$summary): ?>
        <tr><td colspan="7" class="empty-table-message">No components were found for the selected date and queue.</td></tr>
      <?php else: ?>
        <?php foreach ($summary as $component => $values): ?>
          <tr>
            <td><strong><?= e(component_display_name($component, $asset)) ?></strong></td>
            <td><?= e(number_format($values['total'])) ?></td>
            <td><?= e(number_format($values['immediate'])) ?></td>
            <td><?= e(number_format($values['urgent'])) ?></td>
            <td><?= e(number_format($values['low_confidence'])) ?></td>
            <td><?= e(number_format($values['out_of_kb'])) ?></td>
            <td><a class="review-btn" href="<?= e(app_url('admin_portal/hitl/daily_component_cases.php?date=' . rawurlencode($date) . '&queue=' . rawurlencode($queue) . '&asset=' . rawurlencode($asset) . '&component=' . rawurlencode($component))) ?>">View Cases</a></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_ui_footer(); ?>
