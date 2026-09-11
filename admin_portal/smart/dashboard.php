<?php
/**
 * SmartOps Source: admin_portal/smart/dashboard.php
 * Purpose: Smart Maintenance Intelligence dashboard UI and analytics containers.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/../admin_auth.php';
require_once __DIR__ . '/../../includes/admin_layout.php';

// =============================================================================
// SECTION: Authentication and Access Control
// =============================================================================
require_admin();


// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
admin_ui_header(
    'Smart Maintenance Intelligence',
    'smart',
    'AI complaint intake, ticket routing status, complaint trends, category distribution, and unresolved complaint aging.'
);
?>
<!-- SECTION: KPI Cards -->
<?php admin_dashboard_kpis([
    ['title' => 'Total Maintenance Complaints', 'value' => '0', 'value_id' => 'smTotalComplaint', 'description' => 'Complaints received in the rolling 7-day window'],
    ['title' => 'Unresolved Complaints', 'value' => '0', 'value_id' => 'smUnsolvedComplaint', 'description' => 'Current unresolved complaints · includes older cases'],
    ['title' => 'New Complaints', 'value' => '0', 'value_id' => 'smNewComplaintToday', 'description' => 'Complaints received on the current day'],
], 'Smart Maintenance summary KPIs'); ?>

<!-- SECTION: AI Ticket Routing Summary -->
<section class="chart-card hitl-status-card routing-summary-card">
  <div class="chart-header"><div><h2>AI Ticket Routing Summary</h2><p>Tickets received in the current rolling seven-day window, routed automatically or escalated for human review.</p></div></div>
  <div class="hitl-status-grid routing-status-grid">
    <article class="hitl-status-panel hitl-required-panel routing-status-panel" aria-label="HITL required cases">
      <h3>HITL Required</h3>
      <strong><span id="hitlRequiredCount">0</span> <span class="case-unit">cases</span></strong>
      <p id="hitlRequiredPercent">0.0% of total tickets</p>
    </article>
    <article class="hitl-status-panel no-hitl-panel routing-status-panel" aria-label="No HITL required cases">
      <h3>No HITL Required</h3>
      <strong><span id="noHitlCount">0</span> <span class="case-unit">cases</span></strong>
      <p id="noHitlPercent">0.0% of total tickets</p>
    </article>
  </div>
</section>

<!-- SECTION: Maintenance Analytics Charts -->
<section class="smart-analytics-grid">
  <div class="chart-card smart-trend-middle-card smart-analytics-main">
    <div class="chart-header trend-chart-header">
      <div>
        <h2>Daily Complaint Resolution Trend</h2>
        <p>Rolling seven-day intake and completion events. Unfinished cases carry forward in the daily operational summary until completed.</p>
      </div>
      <div class="trend-inline-legend" aria-label="Trend chart legend">
        <span><i class="trend-legend-line trend-legend-blue"></i>Complaints Received</span>
        <span><i class="trend-legend-line trend-legend-teal"></i>Cases Completed</span>
      </div>
    </div>
    <div class="chart-box smart-middle-chart-box"><canvas id="smTrendChart"></canvas></div>
  </div>

  <div class="smart-analytics-side">
    <div class="chart-card smart-side-chart-card">
      <div class="chart-header"><div><h2>Unresolved Complaints by Category</h2><p>Current unresolved complaints grouped by maintenance category. Click a bar to view the related cases.</p></div></div>
      <div class="chart-box smart-lower-chart-box"><canvas id="smAssetChart"></canvas></div>
    </div>
    <div class="chart-card smart-side-chart-card">
      <div class="chart-header"><div><h2>Unresolved Complaint Aging</h2><p>Current unresolved complaints grouped by how long they have remained unresolved. Click a bar for details.</p></div></div>
      <div class="chart-box smart-lower-chart-box"><canvas id="smAgingChart"></canvas></div>
    </div>
  </div>
</section>


<div class="smart-trend-modal" id="smartTrendSummaryModal" aria-hidden="true">
  <div class="smart-trend-modal-card" role="dialog" aria-modal="true" aria-labelledby="smartTrendModalTitle">
    <button type="button" class="smart-trend-modal-close" data-smart-trend-close aria-label="Close">×</button>
    <span class="smart-trend-modal-eyebrow">Daily operational summary</span>
    <h2 id="smartTrendModalTitle">Complaint and Completion Details</h2>
    <p class="smart-trend-modal-date" id="smartTrendModalDate">Selected date</p>
    <div class="smart-trend-modal-grid">
      <div><span>Total Complaints</span><strong id="smartTrendModalTotal">0</strong></div>
      <div><span>Completed Cases</span><strong id="smartTrendModalCompleted">0</strong></div>
      <div><span>Open Backlog at End of Day</span><strong id="smartTrendModalUnsolved">0</strong></div>
      <div><span>Same-Day Completion Rate</span><strong id="smartTrendModalCompletionRate">0%</strong></div>
    </div>
    <p class="smart-trend-modal-note" id="smartTrendModalNote"></p>
    <div class="smart-trend-modal-actions">
      <button type="button" data-smart-trend-close>Close</button>
      <a id="smartTrendModalViewCases" href="trend_detail.php">View Cases</a>
    </div>
  </div>
</div>
<?php admin_ui_footer(['https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js']); ?>
