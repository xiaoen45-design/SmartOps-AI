<?php
/**
 * SmartOps Source: admin_portal/smart/asset_detail.php
 * Purpose: Admin Portal / Smart: Asset Detail server-side page, endpoint, or reusable module.
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
admin_ui_header('Hotel Asset Summary', $activeDashboard, 'Loading selected asset...', true, $backDashboard);
?>
<!-- SECTION: Detail Content -->
<section class="detail-page" data-smart-page="asset-detail">
  <!-- SECTION: KPI Cards -->
  <section class="kpi-container four-kpi detail-kpi-grid">
    <div class="kpi-card"><h3>Total Open Cases</h3><h2 id="assetTotal">0</h2></div>
    <div class="kpi-card"><h3>High</h3><h2 id="assetHigh">0</h2></div>
    <div class="kpi-card"><h3>Medium Severity</h3><h2 id="assetMedium">0</h2></div>
    <div class="kpi-card"><h3>Low Severity</h3><h2 id="assetLow">0</h2></div>
  </section>

  <!-- SECTION: Charts / Analytics -->
  <section class="chart-card">
    <div class="table-header"><div><h2>Component Categories</h2><p>Select a component to open its tailored case records.</p></div></div>
    <div class="table-box detail-table-box">
      <!-- SECTION: Data Table -->
      <table class="dashboard-table detail-table">
        <thead><tr><th>Component</th><th>Total Cases</th><th>High</th><th>Medium</th><th>Low</th><th>Action</th></tr></thead>
        <tbody id="assetComponentBody"></tbody>
      </table>
    </div>
  </section>
</section>
<?php admin_ui_footer(['https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js']); ?>
