<?php
/**
 * SmartOps Source: admin_portal/overview/trend_cases.php
 * Purpose: Admin Portal / Overview: Trend Cases server-side page, endpoint, or reusable module.
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
admin_ui_header('Selected Date Cases', 'overview', 'Loading selected date cases...', true);
?>
<!-- SECTION: Detail Content -->
<section class="detail-page" data-overview-page="trend-cases-detail">
  <!-- SECTION: KPI Cards -->
  <section class="kpi-container three-kpi detail-kpi-grid">
    <div class="kpi-card summary-only"><h3>Complaints Received</h3><h2 id="detailTotalCases">0</h2></div>
    <div class="kpi-card summary-only"><h3>Completed on Date</h3><h2 id="detailCompletedCases">0</h2></div>
    <div class="kpi-card summary-only"><h3>Open Backlog at End of Day</h3><h2 id="detailOpenCases">0</h2></div>
  </section>

  <!-- SECTION: Charts / Analytics -->
  <section class="chart-card">
    <div class="table-header"><div><h2>Selected Date Case List</h2><p id="detailRecordCount">Showing 0 record(s)</p></div></div>
    <div class="table-controls">
      <input id="detailSearch" type="search" placeholder="Search Case ID, room, category, component, priority or status">
      <button id="detailReset" class="secondary-btn" type="button">Reset</button>
    </div>
    <div class="table-box detail-table-box">
      <!-- SECTION: Data Table -->
      <table class="dashboard-table detail-table">
        <thead><tr><th>Case ID</th><th>Date</th><th>Room</th><th>Complaint Category</th><th>Component</th><th>Priority</th><th>Status</th></tr></thead>
        <tbody id="detailCaseBody"></tbody>
      </table>
    </div>
  </section>
</section>
<?php admin_ui_footer(['https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js']); ?>
