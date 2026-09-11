<?php
/**
 * SmartOps Source: admin_portal/hitl/dashboard.php
 * Purpose: Human-in-the-Loop dashboard metrics, seven-day summaries, and escalation chart data.
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


// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
admin_ui_header(
    'Human-In-The-Loop Escalation Dashboard',
    'hitl',
    'Manager review workload, escalation breakdown, and pending cases requiring attention.'
);

$dbMessage = database_ready_message();
if ($dbMessage !== '') { ?>
  <!-- SECTION: Content Card -->
  <section class="card"><h2>Database is not ready</h2><p><?= e($dbMessage) ?></p></section>
<?php admin_ui_footer(); exit; }

$hasHitlCreatedAt = db_column_exists('hitl_cases', 'created_at');

// =============================================================================
// SECTION: Database Queries and Data Preparation
// =============================================================================
$hitlRows = fetch_all("SELECT h.*, s.ai_confidence, s.created_at AS ticket_created_at
                       FROM hitl_cases h
                       LEFT JOIN smart_maintenance_tickets s ON s.case_id = h.case_id
                       ORDER BY h.id");

$eventTimestamps = [];
foreach ($hitlRows as &$row) {
    $hitlTs = $hasHitlCreatedAt ? strtotime((string)($row['created_at'] ?? '')) : false;
    $ticketTs = strtotime((string)($row['ticket_created_at'] ?? ''));
    $row['_dashboard_ts'] = $hitlTs ?: $ticketTs ?: 0;
    if ($row['_dashboard_ts']) $eventTimestamps[] = (int)$row['_dashboard_ts'];
}
unset($row);
// Anchor every 7-day chart to today's Kuala Lumpur date. This keeps the
// dashboard rolling forward even on days when no new HITL case is received.
[$sevenDayStart, $sevenDayEnd, $referenceDay] = hitl_seven_day_window_bounds();

$isPending = static function(array $row): bool {
    $value = strtolower(trim((string)($row['review_status'] ?? '')));
    return $value === '' || str_contains($value, 'pending') || str_contains($value, 'review');
};

$pendingRows = array_values(array_filter($hitlRows, $isPending));
$totalPending = count($pendingRows);
$criticalCount = count(array_filter(
    $pendingRows,
    static fn(array $row): bool => hitl_primary_escalation_trigger($row) === 'Critical'
));
$highSeverityCount = count(array_filter(
    $pendingRows,
    static fn(array $row): bool => hitl_primary_escalation_trigger($row) === 'High'
));
$lowConfidenceCount = count(array_filter(
    $pendingRows,
    static fn(array $row): bool => hitl_primary_escalation_trigger($row) === 'Low Confidence'
));

$rawOutOfKbRows = array_values(array_filter($pendingRows, 'hitl_is_out_of_knowledge_case'));
$outOfKbCount = count($rawOutOfKbRows);

// The action card above uses the current pending workload. The charts below
// use all cases that entered HITL during the latest seven-day reporting window,
// including cases that were later approved, dispatched, assigned, or completed.
$sevenDayRows = array_values(array_filter(
    $hitlRows,
    static fn(array $row): bool => (int)($row['_dashboard_ts'] ?? 0) >= $sevenDayStart
        && (int)($row['_dashboard_ts'] ?? 0) < $sevenDayEnd
));

$triggerBreakdown = [
    'Immediate' => 0,
    'Urgent' => 0,
    'Low Confidence' => 0,
    'Out of Knowledge Base' => 0,
];
foreach ($sevenDayRows as $row) {
    $trigger = hitl_primary_escalation_trigger($row);
    if ($trigger === 'Critical') $triggerBreakdown['Immediate']++;
    elseif ($trigger === 'High') $triggerBreakdown['Urgent']++;
    elseif ($trigger === 'Low Confidence') $triggerBreakdown['Low Confidence']++;
    elseif ($trigger === 'Out of Knowledge Base') $triggerBreakdown['Out of Knowledge Base']++;
}

// Always show the five core maintenance categories in a stable D10-D50 order.
// Counts include every case that entered HITL during the seven-day window.
$maintenanceCategories = ['Conveying', 'Plumbing', 'HVAC', 'Fire Protection', 'Electrical'];
$assetBreakdown = array_fill_keys($maintenanceCategories, 0);
foreach ($sevenDayRows as $row) {
    // Use the canonical asset name for aggregation. asset_display_name()
    // adds the D10-D50 display prefix, while the fixed chart buckets use
    // the canonical names below (Conveying, Plumbing, HVAC, etc.).
    $asset = clean_asset_name($row['hotel_asset'] ?? 'Unknown');
    if (array_key_exists($asset, $assetBreakdown)) {
        $assetBreakdown[$asset]++;
    }
}

$trendLabels = [];
$trendDates = [];
$trendCritical = [];
$trendHigh = [];
$trendLowConfidence = [];
$trendOokb = [];
for ($i = 6; $i >= 0; $i--) {
    $dayTs = strtotime("-{$i} days", $referenceDay);
    $dayKey = date('Y-m-d', $dayTs);
    $trendLabels[] = date('d M', $dayTs);
    $trendDates[] = $dayKey;

    $dailyCounts = [
        'Critical' => 0,
        'High' => 0,
        'Low Confidence' => 0,
        'Out of Knowledge Base' => 0,
    ];

    // The daily trend is historical volume, so approved or completed cases
    // remain counted on the date they entered HITL. Current queue charts below
    // still use $pendingRows.
    foreach ($hitlRows as $row) {
        if (date('Y-m-d', (int)$row['_dashboard_ts']) !== $dayKey) continue;
        $trigger = hitl_primary_escalation_trigger($row);
        if (array_key_exists($trigger, $dailyCounts)) {
            $dailyCounts[$trigger]++;
        }
    }

    $trendCritical[] = $dailyCounts['Critical'];
    $trendHigh[] = $dailyCounts['High'];
    $trendLowConfidence[] = $dailyCounts['Low Confidence'];
    $trendOokb[] = $dailyCounts['Out of Knowledge Base'];
}

$hitlDashboardData = [
    'triggerLabels' => array_keys($triggerBreakdown),
    'triggerValues' => array_values($triggerBreakdown),
    'triggerRoutes' => ['Critical', 'High', 'Low Confidence', 'Out of Knowledge Base'],
    'assetLabels' => array_keys($assetBreakdown),
    'assetValues' => array_values($assetBreakdown),
    'trendLabels' => $trendLabels,
    'trendDates' => $trendDates,
    'trendCritical' => $trendCritical,
    'trendHigh' => $trendHigh,
    'trendLowConfidence' => $trendLowConfidence,
    'trendOokb' => $trendOokb,
];

$bannerStateClass = $criticalCount > 0 ? ' has-immediate is-pulsing' : '';
?>
<!-- SECTION: KPI Cards -->
<section class="hitl-custom-kpi-row" aria-label="HITL management priorities">
<?php if ($criticalCount > 0): ?>
<a class="hitl-action-banner hitl-immediate-banner<?= e($bannerStateClass) ?>" href="<?= e(app_url('admin_portal/hitl/trigger_detail.php?trigger=Critical&source=hitl')) ?>">
  <div>
    <h2>Immediate Safety Action Required</h2>
    <p>Safety-critical cases requiring immediate manager review and action.</p>
  </div>
  <div class="hitl-action-count" aria-label="<?= e(number_format($criticalCount)) ?> immediate safety-critical cases">
    <strong><?= e(number_format($criticalCount)) ?></strong>
  </div>
</a>
<?php else: ?>
<div class="hitl-action-banner hitl-immediate-banner is-zero" aria-label="No immediate safety-critical cases">
  <div>
    <h2>Immediate Safety Action Required</h2>
    <p>No safety-critical cases currently require immediate manager action.</p>
  </div>
  <div class="hitl-action-count"><strong>0</strong></div>
</div>
<?php endif; ?>

<a class="hitl-pending-action-card" href="<?= e(app_url('admin_portal/hitl/pending_review.php?source=hitl')) ?>" aria-label="View all pending manager review cases">
  <div class="hitl-pending-left-panel">
    <div class="hitl-pending-card-copy">
      <h2>Pending Manager Review</h2>
      <p>Current pending workload by review queue.</p>
    </div>
    <div class="hitl-pending-breakdown" aria-label="Current pending queue breakdown">
      <span><b>Immediate</b><strong><?= e(number_format($criticalCount)) ?></strong></span>
      <span><b>Urgent</b><strong><?= e(number_format($highSeverityCount)) ?></strong></span>
      <span><b>Low Confidence</b><strong><?= e(number_format($lowConfidenceCount)) ?></strong></span>
      <span><b>Out of KB</b><strong><?= e(number_format($outOfKbCount)) ?></strong></span>
    </div>
  </div>
  <div class="hitl-pending-total-panel" aria-label="<?= e(number_format($totalPending)) ?> cases awaiting manager review">
    <div class="hitl-pending-total-copy">
      <strong><?= e(number_format($totalPending)) ?></strong>
      <span>
        <b>Pending cases</b>
        <small>Awaiting a manager decision</small>
      </span>
    </div>
    <span class="hitl-pending-link">View pending cases →</span>
  </div>
</a>
</section>

<!-- SECTION: Charts / Analytics -->
<section class="card chart-card hitl-trend-full">
  <div class="chart-title-row clean-title"><div><h2>Daily HITL Escalation Volume</h2><p>All HITL escalations are counted on the day they entered review, even after approval or dispatch. Hover or click a point to view the trigger breakdown.</p></div></div>
  <div class="chart-box hitl-trend-box"><canvas id="hitlTrendChart"></canvas></div>
</section>

<section class="dashboard-two-col-grid hitl-third-layer">
  <div class="card chart-card">
    <div class="chart-title-row clean-title"><div><h2>7-Day HITL Review Queue Breakdown</h2><p>All cases that entered HITL during the latest seven-day reporting window.</p></div></div>
    <div class="chart-box hitl-bottom-chart-box"><canvas id="hitlTriggerChart"></canvas></div>
  </div>
  <div class="card chart-card">
    <div class="chart-title-row clean-title"><div><h2>7-Day HITL Cases by Category</h2><p>All cases that entered HITL during the latest seven-day reporting window.</p></div></div>
    <div class="chart-box hitl-bottom-chart-box"><canvas id="hitlAssetChart"></canvas></div>
  </div>
</section>

<div class="review-modal hitl-trend-summary-modal" id="hitlTrendSummaryModal" aria-hidden="true">
  <div class="review-modal-card hitl-trend-summary-card" role="dialog" aria-modal="true" aria-labelledby="hitlTrendSummaryTitle">
    <button class="review-modal-close hitl-trend-summary-close" type="button" aria-label="Close">×</button>
    <h2 id="hitlTrendSummaryTitle">Daily HITL Escalation Summary</h2>
    <p class="hitl-trend-summary-subtitle" id="hitlTrendSummarySubtitle">Selected date escalation overview</p>
    <div class="review-detail-grid hitl-trend-detail-grid" id="hitlTrendSummaryGrid"></div>
    <div class="hitl-trend-summary-note" id="hitlTrendSummaryNote"></div>
    <div class="review-modal-actions">
      <button type="button" class="review-secondary hitl-trend-summary-close-button">Close</button>
    </div>
  </div>
</div>

<!-- SECTION: HITL Dashboard JavaScript Configuration -->
<script>
window.hitlDashboardData = <?= json_encode($hitlDashboardData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.hitlTriggerUrl = <?= json_encode(app_url('admin_portal/hitl/seven_day_summary.php')) ?>;
window.hitlAssetUrl = <?= json_encode(app_url('admin_portal/hitl/seven_day_summary.php')) ?>;
window.hitlDailySummaryUrl = <?= json_encode(app_url('admin_portal/hitl/daily_summary.php')) ?>;
</script>
<?php admin_ui_footer(['https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js']); ?>
