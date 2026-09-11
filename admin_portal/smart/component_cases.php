<?php
/**
 * SmartOps Source: admin_portal/smart/component_cases.php
 * Purpose: Admin Portal / Smart: Component Cases server-side page, endpoint, or reusable module.
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
$sourceDashboard = strtolower(trim((string)($_GET['source'] ?? 'smart')));
$fromOverview = $sourceDashboard === 'overview';
$activeDashboard = $fromOverview ? 'overview' : 'smart';
$backDashboard = $fromOverview
    ? 'admin_portal/overview/dashboard.php'
    : 'admin_portal/smart/dashboard.php';

// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
admin_ui_header('Component Case Records', $activeDashboard, 'Loading tailored maintenance cases...', true, $backDashboard);
?>
<!-- SECTION: Detail Content -->
<section class="detail-page" data-smart-page="component-cases">
  <!-- SECTION: KPI Cards -->
  <section class="kpi-container four-kpi detail-kpi-grid">
    <div class="kpi-card"><h3>Total Cases</h3><h2 id="componentTotal">0</h2></div>
    <div class="kpi-card"><h3>High</h3><h2 id="componentHigh">0</h2></div>
    <div class="kpi-card"><h3>Medium Severity</h3><h2 id="componentMedium">0</h2></div>
    <div class="kpi-card"><h3>Low Severity</h3><h2 id="componentLow">0</h2></div>
  </section>
  <!-- SECTION: Charts / Analytics -->
  <section class="chart-card">
    <div class="table-header">
      <div>
        <h2>Tailored Maintenance Cases</h2>
        <p id="detailRecordCount">Showing 0 record(s)</p>
      </div>
    </div>
    <div class="table-controls">
      <input id="detailSearch" type="search" placeholder="Search Case ID, room, component or issue">
      <button id="detailReset" class="secondary-btn" type="button">Reset</button>
    </div>
    <div class="table-box detail-table-box">
      <table class="dashboard-table detail-table">
        <thead>
          <tr>
            <th>Case ID</th><th>Room</th><th>Component</th><th>Severity</th>
            <th>Priority</th><th>Status</th><th>Action</th>
          </tr>
        </thead>
        <tbody id="detailCaseBody"></tbody>
      </table>
    </div>
  </section>
</section>
<!-- SECTION: Case Detail Modal -->
<div class="case-modal" id="caseModal" aria-hidden="true">
  <div class="case-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="caseModalTitle">
    <button class="modal-close" id="caseModalClose" type="button" aria-label="Close">×</button>
    <h2 id="caseModalTitle">Case Details</h2>
    <div id="caseModalContent" class="case-detail-grid"></div>
  </div>
</div>
<?php admin_ui_footer(['https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js']); ?>
