<?php
/**
 * SmartOps Source: admin_portal/hitl/seven_day_summary.php
 * Purpose: Admin Portal / HITL: Seven Day Summary server-side page, endpoint, or reusable module.
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

$queue = hitl_daily_valid_queue((string)($_GET['trigger'] ?? $_GET['queue'] ?? 'All'));
$asset = trim((string)($_GET['asset'] ?? ''));
$queueLabel = hitl_daily_queue_label($queue);
$rows = hitl_seven_day_fetch_rows($queue, $asset);
$windowLabel = hitl_seven_day_window_label();

$isComponentView = $asset !== '';
$title = $isComponentView
    ? asset_display_name($asset) . ' 7-Day HITL Category Summary'
    : $queueLabel . ' 7-Day HITL Summary';
$subtitle = $isComponentView
    ? 'Component-level escalation summary for ' . $windowLabel . '.'
    : 'Category-level escalation summary for ' . $windowLabel . '.';


// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
admin_ui_header($title, 'hitl', $subtitle, true);

$summary = [];
if ($isComponentView) {
    foreach ($rows as $row) {
        $key = (string)($row['component'] ?: 'Unknown');
        if (!isset($summary[$key])) $summary[$key] = ['rows' => []];
        $summary[$key]['rows'][] = $row;
    }
} else {
    foreach ($rows as $row) {
        $key = asset_display_name($row['hotel_asset'] ?? 'Other');
        if (!isset($summary[$key])) $summary[$key] = ['rows' => []];
        $summary[$key]['rows'][] = $row;
    }
}
foreach ($summary as &$entry) {
    $entry = array_merge($entry, hitl_daily_breakdown($entry['rows']));
}
unset($entry);
uasort($summary, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);
?>
<!-- SECTION: KPI Cards -->
<section class="kpis four detail-summary-kpis daily-hitl-kpis">
  <div class="kpi"><h3>Reporting Window</h3><h2 class="text-kpi"><?= e($windowLabel) ?></h2></div>
  <div class="kpi"><h3>Total HITL Cases</h3><h2><?= e(number_format(count($rows))) ?></h2></div>
  <div class="kpi"><h3><?= $isComponentView ? 'Components' : 'Maintenance Categories' ?></h3><h2><?= e(number_format(count($summary))) ?></h2></div>
  <div class="kpi"><h3>Selected Queue</h3><h2 class="text-kpi"><?= e($queueLabel) ?></h2></div>
</section>

<!-- SECTION: Content Card -->
<section class="card daily-hitl-summary-card">
  <div class="table-header">
    <div>
      <h2><?= $isComponentView ? 'Component Categories' : 'Maintenance Category Summary' ?></h2>
      <p><?= $isComponentView
          ? 'Select a component to view the cases recorded during the seven-day window.'
          : 'Select a category to view its component-level seven-day HITL breakdown.' ?></p>
    </div>
  </div>
  <div class="table-box">
    <!-- SECTION: Data Table -->
    <table class="dashboard-table component-summary-table daily-summary-table">
      <thead>
        <tr>
          <th><?= $isComponentView ? 'Component' : 'Category' ?></th>
          <th>Total Cases</th><th>Immediate</th><th>Urgent</th><th>Low Confidence</th><th>Out of KB</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$summary): ?>
        <tr><td colspan="7" class="empty-table-message">No HITL cases were recorded during the selected seven-day window.</td></tr>
      <?php else: ?>
        <?php foreach ($summary as $name => $values):
          if ($isComponentView) {
              $url = app_url('admin_portal/hitl/seven_day_cases.php?queue=' . rawurlencode($queue)
                  . '&asset=' . rawurlencode($asset)
                  . '&component=' . rawurlencode($name));
          } else {
              $url = app_url('admin_portal/hitl/seven_day_summary.php?queue=' . rawurlencode($queue)
                  . '&asset=' . rawurlencode($name));
          }
        ?>
          <tr>
            <td><strong><?= e($isComponentView ? component_display_name($name, $asset) : $name) ?></strong></td>
            <td><?= e(number_format($values['total'])) ?></td>
            <td><?= e(number_format($values['immediate'])) ?></td>
            <td><?= e(number_format($values['urgent'])) ?></td>
            <td><?= e(number_format($values['low_confidence'])) ?></td>
            <td><?= e(number_format($values['out_of_kb'])) ?></td>
            <td><a class="review-btn" href="<?= e($url) ?>"><?= $isComponentView ? 'View Cases' : 'View Summary' ?></a></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_ui_footer(); ?>
