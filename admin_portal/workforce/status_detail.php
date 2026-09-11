<?php
/**
 * SmartOps Source: admin_portal/workforce/status_detail.php
 * Purpose: Admin Portal / Workforce: Status Detail server-side page, endpoint, or reusable module.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../admin_auth.php';

// =============================================================================
// SECTION: Authentication and Access Control
// =============================================================================
require_admin();
refresh_live_sla_statuses();

$status = trim($_GET['status'] ?? 'Waiting Acceptance');
$allowed = ['Waiting Acceptance','In Progress','Outsourcing','Senior Support','Parts Required'];
if (!in_array($status, $allowed, true)) $status = 'Waiting Acceptance';


// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
admin_ui_header($status . ' Work Orders', 'workforce', 'All work orders related to the selected status are shown below.', true);


// =============================================================================
// SECTION: Workforce Status Where
// =============================================================================
function workforce_status_where(string $status, array &$params): string {
    if ($status === 'Waiting Acceptance') return "work_stage='Assigned - Not Started'";
    if ($status === 'In Progress') return "work_stage='Repair In Progress'";
    if ($status === 'Outsourcing') return "support_reason='Outsourcing' AND work_stage IN ('Pending Outsourcing','Outsourced','Pending Reassignment')";
    if ($status === 'Senior Support') return "support_reason='Senior Support' AND work_stage='Pending Senior Assignment'";
    return "support_reason='Parts Required' AND work_stage IN ('Waiting for Parts','Pending Reassignment')";
}

// =============================================================================
// SECTION: Workforce Asset Label
// =============================================================================
function workforce_asset_label(string $asset): string {
    $clean = clean_asset_name($asset);
    $lower = strtolower($clean);
    if (str_contains($lower, 'fire')) return 'Fire Protection';
    if (str_contains($lower, 'elevator') || str_contains($lower, 'convey')) return 'Conveying';
    if (str_contains($lower, 'hvac')) return 'HVAC';
    if (str_contains($lower, 'plumb')) return 'Plumbing';
    if (str_contains($lower, 'electric')) return 'Electrical';
    return 'Other';
}

$params = [];
$where = workforce_status_where($status, $params);

// =============================================================================
// SECTION: Database Queries and Data Preparation
// =============================================================================
$cases = fetch_all("SELECT * FROM workforce_cases WHERE $where ORDER BY assigned_at DESC, case_id DESC", $params);
$high = 0;
$breached = 0;
foreach ($cases as $case) {
    if (stripos((string)($case['severity'] ?? ''), 'high') !== false) $high++;
    if (is_sla_breach($case)) $breached++;
}
$assets = ['HVAC','Plumbing','Electrical','Fire Protection','Conveying','Other'];
?>
<!-- SECTION: KPI Cards -->
<section class="kpis one-row three workforce-detail-kpis">
  <div class="kpi"><h3>Selected Status</h3><h2 class="text-value"><?= e($status) ?></h2></div>
  <div class="kpi"><h3>Total Work Orders</h3><h2 id="statusVisibleTotal"><?= e(number_format(count($cases))) ?></h2></div>
  <div class="kpi"><h3>SLA Breached</h3><h2><?= e(number_format($breached)) ?></h2></div>
</section>

<!-- SECTION: Data Table -->
<section class="card detail-table-card">
  <div class="table-header status-filter-header">
    <div>
      <h2><?= e($status) ?> Case List</h2>
      <p>Use the hotel-asset filters to narrow the cases. The default view contains every related work order.</p>
    </div>
  </div>
  <div class="asset-filter-row" role="group" aria-label="Hotel asset filter">
    <button type="button" class="case-filter-btn is-active" data-asset-filter="all">All Assets</button>
    <?php foreach ($assets as $asset): ?><button type="button" class="case-filter-btn" data-asset-filter="<?= e($asset) ?>"><?= e($asset) ?></button><?php endforeach; ?>
  </div>
  <p class="table-result-count" id="statusResultCount">Showing <?= e(number_format(count($cases))) ?> case(s)</p>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Case ID</th><th>Room</th><th>Hotel Asset</th><th>Component</th><th>Severity</th><th>Priority</th><th>Work Stage</th><th>SLA Status</th><th>Assigned Technician</th></tr></thead>
      <tbody id="statusCaseBody">
      <?php if (!$cases): ?><tr><td colspan="9" class="empty-table-message">No work orders were found for this status.</td></tr><?php endif; ?>
      <?php foreach ($cases as $case): $assetFilter = workforce_asset_label((string)$case['hotel_asset']); $assetLabel = asset_display_name((string)$case['hotel_asset']); ?>
        <tr data-asset="<?= e($assetFilter) ?>">
          <td><strong><?= e($case['case_id']) ?></strong></td>
          <td><?= e($case['room']) ?></td>
          <td><?= e($assetLabel) ?></td>
          <td><?= e(component_display_name($case['component'] ?? '-', $case['hotel_asset'] ?? '')) ?></td>
          <td><?= severity_badge_html($case['severity'] ?? '') ?></td>
          <td><?= e(priority_display_name($case['priority'] ?? '')) ?></td>
          <td><?= e($case['work_stage']) ?></td>
          <td><?= e($case['sla_status'] ?: $case['overall_sla_status']) ?></td>
          <td><?= e($case['assigned_technician_id'] ?: 'Unassigned') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const buttons = [...document.querySelectorAll('[data-asset-filter]')];
  const rows = [...document.querySelectorAll('#statusCaseBody tr[data-asset]')];
  const count = document.getElementById('statusResultCount');
  const total = document.getElementById('statusVisibleTotal');


  // =============================================================================
  // SECTION: Apply Filter
  // =============================================================================
  function applyFilter(filter) {
    let visible = 0;
    rows.forEach(row => {
      const show = filter === 'all' || row.dataset.asset === filter;
      row.hidden = !show;
      if (show) visible++;
    });
    buttons.forEach(button => button.classList.toggle('is-active', button.dataset.assetFilter === filter));
    if (count) count.textContent = `Showing ${visible.toLocaleString()} case(s)`;
    if (total) total.textContent = visible.toLocaleString();
  }

  buttons.forEach(button => button.addEventListener('click', () => applyFilter(button.dataset.assetFilter)));
});
</script>
<?php admin_ui_footer(); ?>
