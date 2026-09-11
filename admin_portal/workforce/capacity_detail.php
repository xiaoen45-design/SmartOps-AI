<?php
/**
 * SmartOps Source: admin_portal/workforce/capacity_detail.php
 * Purpose: Admin Portal / Workforce: Capacity Detail server-side page, endpoint, or reusable module.
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

$group = trim($_GET['group'] ?? 'HVAC');
$allowed = ['HVAC', 'Plumbing', 'Electrical', 'Fire Protection', 'Conveying', 'Technical Specialist'];
if (!in_array($group, $allowed, true)) {
    $group = 'HVAC';
}

$isSpecialistGroup = $group === 'Technical Specialist';


// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
admin_ui_header(
    $group . ' Capacity & Assigned Workload',
    'workforce',
    '',
    true
);

if ($isSpecialistGroup) {
    $techs = fetch_technicians_with_workload('Technical Specialist');

    // =============================================================================
    // SECTION: Database Queries and Data Preparation
    // =============================================================================
    $cases = fetch_all(
        "SELECT w.*, CASE WHEN h.case_id IS NULL THEN 0 ELSE 1 END AS is_hitl_case
         FROM workforce_cases w
         LEFT JOIN hitl_cases h ON h.case_id=w.case_id
         INNER JOIN technicians t ON t.technician_id = w.assigned_technician_id
         WHERE w.work_stage <> 'Completed'
           AND t.is_active = 1
           AND t.role = 'Technical Specialist'
         ORDER BY w.assigned_at DESC, w.case_id DESC"
    );
} else {
    $techs = fetch_technicians_with_workload('Technician', $group);
    $cases = fetch_all(
        "SELECT w.*, CASE WHEN h.case_id IS NULL THEN 0 ELSE 1 END AS is_hitl_case
         FROM workforce_cases w
         LEFT JOIN hitl_cases h ON h.case_id=w.case_id
         INNER JOIN technicians t ON t.technician_id = w.assigned_technician_id
         WHERE w.work_stage <> 'Completed'
           AND t.is_active = 1
           AND t.role = 'Technician'
           AND t.department = ?
         ORDER BY w.assigned_at DESC, w.case_id DESC",
        [$group]
    );
}

$available = 0;
$regularTechnicians = 0;
$technicalSpecialists = 0;
foreach ($techs as $technician) {
    if ((int)($technician['active_tasks'] ?? 0) < SMARTOPS_MAX_ACTIVE_TASKS) {
        $available++;
    }
    if (($technician['role'] ?? '') === 'Technical Specialist') {
        $technicalSpecialists++;
    } else {
        $regularTechnicians++;
    }
}

$teamComposition = $isSpecialistGroup
    ? $technicalSpecialists . ' HITL Technicians covering All Departments'
    : $regularTechnicians . ' regular ' . $group . ' Technicians';

$aiCases = 0;
$hitlCases = 0;
foreach ($cases as $case) {
    if (!empty($case['is_hitl_case'])) {
        $hitlCases++;
    } else {
        $aiCases++;
    }
}
?>
<!-- SECTION: KPI Cards -->
<section class="kpis one-row four workforce-detail-kpis">
  <div class="kpi"><h3><?= $isSpecialistGroup ? 'Total HITL Technician Cases' : 'Total AI Automation Technician Cases' ?></h3><h2><?= e(number_format(count($cases))) ?></h2></div>
  <div class="kpi"><h3>AI Automation Technician Cases</h3><h2><?= e(number_format($aiCases)) ?></h2><p><?= $isSpecialistGroup ? 'Not assigned to this role group' : 'Handled by regular department technicians' ?></p></div>
  <div class="kpi"><h3>HITL Technician Cases</h3><h2><?= e(number_format($hitlCases)) ?></h2><p><?= $isSpecialistGroup ? 'Handled only by Technical Specialists' : 'Shown only under Technical Specialist' ?></p></div>
  <div class="kpi"><h3>Available Team Members</h3><h2><?= e(number_format($available)) ?></h2><p><?= e($teamComposition) ?></p></div>
</section>

<!-- SECTION: Data Table -->
<section class="card detail-table-card">
  <div class="table-header">
    <h2>Assigned Workload Cases</h2>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Case ID</th>
          <th>Room</th>
          <th>Hotel Asset</th>
          <th>Component</th>
          <th>Severity</th>
          <th>Priority</th>
          <th>Work Stage</th>
          <th>Case Type</th>
          <th>Assigned Technician</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$cases): ?>
        <tr class="empty-row"><td colspan="9" class="empty-table-message">No active cases are currently assigned to this group.</td></tr>
      <?php endif; ?>
      <?php foreach ($cases as $case):
          $caseType = !empty($case['is_hitl_case']) ? 'HITL Technician' : 'AI Automation Technician';
      ?>
        <tr>
          <td><strong><?= e($case['case_id']) ?></strong></td>
          <td><?= e($case['room']) ?></td>
          <td><?= e(asset_display_name($case['hotel_asset'])) ?></td>
          <td><?= e($case['component']) ?></td>
          <td><?= severity_badge_html($case['severity'] ?? '') ?></td>
          <td><?= e(priority_display_name($case['priority'] ?? '')) ?></td>
          <td><?= e($case['work_stage']) ?></td>
          <td><span class="case-type-badge <?= $caseType === 'HITL Technician' ? 'case-type-hitl' : 'case-type-ai' ?>"><?= e($caseType) ?></span></td>
          <td><?= e($case['assigned_technician_id'] ?: 'Unassigned') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_ui_footer(); ?>
