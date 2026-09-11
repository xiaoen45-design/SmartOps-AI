<?php
/**
 * SmartOps Source: admin_portal/overview/dashboard.php
 * Purpose: Executive Overview dashboard UI and KPI/chart containers.
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
    'Executive Overview Dashboard',
    'overview',
    'Operational view of complaint intake, AI automation, HITL review, workforce execution, and SLA health.'
);
?>
<!-- SECTION: KPI Cards -->
<?php admin_dashboard_kpis([
    ['title' => 'Tickets Received', 'value' => '0', 'value_id' => 'totalComplaint7', 'description' => 'Rolling 7 days'],
    ['title' => 'Active Work Orders', 'value' => '0', 'value_id' => 'actionWorkOrder', 'description' => 'Current pending, assigned & in progress'],
    ['title' => 'Completed Cases', 'value' => '0', 'value_id' => 'completedCases7', 'description' => 'Rolling 7 days'],
    ['title' => 'AI Automation Rate', 'value' => '0%', 'value_id' => 'aiAutomationRate', 'description' => '0 / 0 tickets', 'description_id' => 'aiAutomationText'],
], 'Executive summary KPIs'); ?>

<!-- SECTION: Operational Alerts / Messages -->
<section class="action-card overview-alerts-card">
  <div class="table-header compact-header">
    <div><h2>Operational Alert</h2><p>Critical operational areas requiring management attention.</p></div>
  </div>
  <div class="alert-grid four-alerts">
    <button type="button" class="alert-box alert-red" data-alert="urgent-hitl" onclick="window.location.href='../hitl/pending_review.php?source=overview'">
      <span class="alert-label">Pending HITL Reviews</span>
      <small>Escalated cases waiting for manager decision.</small>
      <strong id="alertUrgentHitl">0</strong>
      <span class="alert-link">View →</span>
    </button>
    <button type="button" class="alert-box alert-neutral" data-alert="sla-breach" onclick="window.location.href='<?= e(app_url('admin_portal/workforce/sla_department.php?department=All&source=overview')) ?>'">
      <span class="alert-label">SLA Breaches</span>
      <small>Maintenance work orders exceeding SLA target.</small>
      <strong id="alertSla">0</strong>
      <span class="alert-link">View →</span>
    </button>
    <button type="button" class="alert-box alert-neutral" data-alert="support-required" onclick="window.location.href='../workforce/action_support.php?reason=All&source=overview'">
      <span class="alert-label">Support Requests</span>
      <small>Tasks requiring parts, senior support, or outsourcing.</small>
      <strong id="alertSupport">0</strong>
      <span class="alert-link">View →</span>
    </button>
    <button type="button" class="alert-box alert-neutral" data-alert="out-of-knowledge-base" onclick="window.location.href='../hitl/trigger_detail.php?trigger=Out%20of%20Knowledge%20Base&source=overview'">
      <span class="alert-label">Out of Knowledge Base</span>
      <small>Cases the AI cannot classify using the current knowledge base.</small>
      <strong id="alertOutKnowledge">0</strong>
      <span class="alert-link">View →</span>
    </button>
  </div>
</section>

<!-- SECTION: Executive Analytics Charts -->
<section class="overview-analytics-layout" aria-label="Executive analytics charts">
  <div class="chart-card overview-trend-card overview-primary-chart">
    <div class="chart-header trend-chart-header">
      <div>
        <h2>Number of Complaints vs Completed Trend</h2>
        <p>Rolling seven-day intake and completion events. Unfinished cases remain in the operational backlog until completed. Click a bar to view the cases for that date.</p>
      </div>
      <div class="trend-inline-legend" aria-label="Complaint and completion chart legend">
        <span><i class="trend-legend-line trend-legend-blue"></i>Complaints Received</span>
        <span><i class="trend-legend-line trend-legend-teal"></i>Cases Completed</span>
      </div>
    </div>
    <div class="chart-box overview-trend-box"><canvas id="complaintCompletedTrendChart"></canvas></div>
  </div>

  <div class="overview-side-charts">
    <div class="chart-card compact-chart-card clickable-card" id="technicianCapacityCard" data-card-link="technician_capacity.php" role="button" tabindex="0" aria-label="View available technicians by department">
      <div class="chart-header">
        <div>
          <h2>Technician Capacity</h2>
          <p>Current technician availability and capacity used. Click this card to view available technicians by department.</p>
        </div>
      </div>
      <div class="capacity-snapshot">
        <div class="capacity-gauge-wrap"><canvas id="technicianCapacityChart"></canvas></div>
        <div id="technicianCapacityLegend" class="capacity-legend"></div>
      </div>
    </div>

    <div class="chart-card compact-chart-card">
      <div class="chart-header">
        <div>
          <h2>Unresolved Complaints by Category</h2>
          <p>Current unresolved complaints grouped by maintenance category. Click a bar to view the related cases.</p>
        </div>
      </div>
      <div class="chart-box overview-bottom-chart-box"><canvas id="overviewAssetDistributionChart"></canvas></div>
    </div>
  </div>
</section>
<?php admin_ui_footer(['https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js']); ?>
