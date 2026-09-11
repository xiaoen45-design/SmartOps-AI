<?php
/**
 * SmartOps Source: admin_portal/workforce/dashboard.php
 * Purpose: Workforce and Task Monitoring dashboard UI and operational metrics.
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


// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
admin_ui_header(
    'Workforce & Task Monitoring',
    'workforce',
    'Technician availability, work order execution, support actions, and SLA performance monitoring.'
);

$dbMessage = database_ready_message();
if ($dbMessage !== '') { ?>
  <!-- SECTION: Content Card -->
  <section class="card"><h2>Database is not ready</h2><p><?= e($dbMessage) ?></p></section>
<?php admin_ui_footer(); exit; }
refresh_live_sla_statuses();
refresh_all_technician_summaries();
$rollingWindow = rolling_seven_day_window();
$rollingStart = $rollingWindow['start'];
$rollingEndExclusive = $rollingWindow['end_exclusive'];
$rollingWindowLabel = $rollingWindow['label'];
$available = available_technician_count();

// =============================================================================
// SECTION: Database Queries and Data Preparation
// =============================================================================
$activeOpen = scalar("SELECT COUNT(*) FROM workforce_cases WHERE work_stage <> 'Completed'");
$slaBreach = scalar("SELECT COUNT(*) FROM workforce_cases WHERE work_stage <> 'Completed' AND sla_status IN ('Response Breach','Repair Breach','Breach','SLA Violation')");
$supportRequired = scalar("SELECT COUNT(*) FROM workforce_cases WHERE " . workforce_manager_action_where());

$statusBreakdown = [
    'Waiting Acceptance' => scalar("SELECT COUNT(*) FROM workforce_cases WHERE work_stage='Assigned - Not Started'"),
    'In Progress' => scalar("SELECT COUNT(*) FROM workforce_cases WHERE work_stage='Repair In Progress'"),
    'Outsourcing' => scalar("SELECT COUNT(*) FROM workforce_cases WHERE support_reason='Outsourcing' AND work_stage IN ('Pending Outsourcing','Outsourced','Pending Reassignment')"),
    'Senior Support' => scalar("SELECT COUNT(*) FROM workforce_cases WHERE support_reason='Senior Support' AND work_stage='Pending Senior Assignment'"),
    'Parts Required' => scalar("SELECT COUNT(*) FROM workforce_cases WHERE support_reason='Parts Required' AND work_stage IN ('Waiting for Parts','Pending Reassignment')"),
];

$departments = ['HVAC', 'Plumbing', 'Electrical', 'Fire Protection', 'Conveying'];
$capacityLabels = [];
$availableTech = [];
$assignedTask = [];
foreach ($departments as $department) {
    $capacityLabels[] = $department;

    // Show the currently available regular Technicians in each department.
    $availableTech[] = scalar(
        "SELECT COUNT(*)
         FROM technicians
         WHERE is_active=1
           AND status='Available'
           AND role='Technician'
           AND department=?",
        [$department]
    );
    $assignedTask[] = scalar(
        "SELECT COUNT(*)
         FROM workforce_cases w
         INNER JOIN technicians t ON t.technician_id = w.assigned_technician_id
         WHERE w.work_stage <> 'Completed'
           AND t.is_active=1
           AND t.role='Technician'
           AND t.department=?",
        [$department]
    );
}

// Technical Specialists remain a separate role group across all departments.
$capacityLabels[] = 'Technical Specialist';
$availableTech[] = scalar(
    "SELECT COUNT(*)
     FROM technicians
     WHERE is_active=1
       AND status='Available'
       AND role='Technical Specialist'"
);
$assignedTask[] = scalar(
    "SELECT COUNT(*)
     FROM workforce_cases w
     INNER JOIN technicians t ON t.technician_id = w.assigned_technician_id
     WHERE w.work_stage <> 'Completed'
       AND t.is_active=1
       AND t.role='Technical Specialist'"
);

$supportActions = [
    'Outsourcing' => [
        'count' => $statusBreakdown['Outsourcing'],
        'description' => 'Review the technician-selected external service and outsourcing reason.',
        'action' => 'Review & Approve'
    ],
    'Senior Support' => [
        'count' => $statusBreakdown['Senior Support'],
        'description' => 'Assign an available Senior to take over the released case.',
        'action' => 'Assign Senior'
    ],
    'Parts Required' => [
        'count' => $statusBreakdown['Parts Required'],
        'description' => 'Track requested, ordered, ready, and reassigned parts cases.',
        'action' => 'Review & Approve'
    ],
];

$sevenDaySupportCounts = [
    'Outsourcing' => 0,
    'Senior Support' => 0,
    'Parts Required' => 0,
];
$sevenDaySupportRows = fetch_all(
    "SELECT support_reason, COUNT(*) AS total
     FROM workforce_cases
     WHERE support_reason IN ('Outsourcing','Senior Support','Parts Required')
       AND support_requested_at IS NOT NULL
       AND support_requested_at >= ?
       AND support_requested_at < ?
     GROUP BY support_reason",
    [$rollingStart, $rollingEndExclusive]
);
foreach ($sevenDaySupportRows as $supportRow) {
    $supportReason = trim((string)($supportRow['support_reason'] ?? ''));
    if (array_key_exists($supportReason, $sevenDaySupportCounts)) {
        $sevenDaySupportCounts[$supportReason] = (int)($supportRow['total'] ?? 0);
    }
}
$sevenDaySupportTotal = array_sum($sevenDaySupportCounts);

$slaDepartmentLabels = ['HVAC', 'Plumbing', 'Electrical', 'Fire Protection', 'Conveying', 'Out of Knowledge Base'];
$slaDepartmentCounts = array_fill_keys($slaDepartmentLabels, 0);
$slaRows = fetch_all(
    "SELECT w.*, h.hitl_reason AS joined_hitl_reason
     FROM workforce_cases w
     LEFT JOIN hitl_cases h ON h.case_id = w.case_id
     WHERE COALESCE(w.case_created_at,w.assigned_at,w.support_requested_at,w.completed_at) >= ?
       AND COALESCE(w.case_created_at,w.assigned_at,w.support_requested_at,w.completed_at) < ?",
    [$rollingStart, $rollingEndExclusive]
);
foreach ($slaRows as $row) {
    if (!is_sla_breach($row)) continue;
    $department = workforce_sla_department($row);
    if (isset($slaDepartmentCounts[$department])) $slaDepartmentCounts[$department]++;
}
$sevenDaySlaTotal = array_sum($slaDepartmentCounts);
$breachedDepartmentCount = count(array_filter($slaDepartmentCounts, fn(int $count): bool => $count > 0));

$workforceDashboardData = [
    'statusLabels' => array_keys($statusBreakdown),
    'statusValues' => array_values($statusBreakdown),
    'capacityLabels' => $capacityLabels,
    'availableTech' => $availableTech,
    'assignedTask' => $assignedTask,
    'slaDepartmentLabels' => array_keys($slaDepartmentCounts),
    'slaDepartmentValues' => array_values($slaDepartmentCounts),
    'statusDetailUrl' => app_url('admin_portal/workforce/status_detail.php'),
    'capacityDetailUrl' => app_url('admin_portal/workforce/capacity_detail.php'),
    'slaDepartmentDetailUrl' => app_url('admin_portal/workforce/sla_department.php?source=workforce'),
    'slaDepartmentScope' => '7d',
];
?>
<!-- SECTION: KPI Cards -->
<?php admin_dashboard_kpis([
    ['title' => 'Available Technicians', 'value' => number_format($available), 'description' => 'Current status'],
    ['title' => 'Active Work Orders', 'value' => number_format($activeOpen), 'description' => 'Current open tasks'],
    ['title' => 'Support Required', 'value' => number_format($supportRequired), 'description' => 'Current manager actions'],
    ['title' => 'Active SLA Breaches', 'value' => number_format($slaBreach), 'description' => 'Current open breaches · includes older cases'],
], 'Current workforce snapshot'); ?>

<!-- SECTION: Work Order Status and Capacity Charts -->
<section class="dashboard-two-col-grid workforce-two-col-grid">
  <div class="card chart-card">
    <div class="chart-title-row clean-title">
      <div><h2>Current Work Order Status</h2><p>Click a status to view the related active work orders.</p></div>
    </div>
    <div class="chart-box"><canvas id="workOrderStatusChart"></canvas></div>
  </div>

  <div class="card chart-card">
    <div class="chart-title-row clean-title">
      <div><h2>Current Technician Availability vs Assigned Workload</h2><p>Click a department or the Technical Specialist group to inspect related workload cases.</p></div>
    </div>
    <div class="chart-box"><canvas id="capacityWorkloadChart"></canvas></div>
  </div>

  <div class="card action-section current-support-section">
    <div class="chart-title-row clean-title support-section-heading">
      <div>
        <h2>Action Support Required</h2>
        <p>Review technician-submitted requests that still require manager action.</p>
      </div>
      <a class="support-section-all-link" href="<?= e(app_url('admin_portal/workforce/action_support.php?reason=All&source=workforce')) ?>">View all current requests →</a>
    </div>

    <div class="support-action-grid support-action-grid-current">
      <?php foreach ($supportActions as $reason => $item): ?>
        <a class="support-action-card" href="<?= e(app_url('admin_portal/workforce/action_support.php?reason=' . rawurlencode($reason) . '&source=workforce')) ?>">
          <span class="support-action-label"><?= e($reason) ?></span>
          <strong><?= e(number_format($item['count'])) ?></strong>
          <p><?= e($item['description']) ?></p>
          <span class="support-action-link"><?= e($item['action']) ?> →</span>
        </a>
      <?php endforeach; ?>
    </div>

    <a class="support-history-card" href="<?= e(app_url('admin_portal/workforce/support_history.php?reason=All')) ?>" aria-label="View support request history for the current rolling seven-day window">
      <div class="support-history-intro">
        <span>Support Request History</span>
        <strong><?= e(number_format($sevenDaySupportTotal)) ?> request<?= $sevenDaySupportTotal === 1 ? '' : 's' ?> in the current 7-day window</strong>
        <p>Review pending and processed technician support requests.</p>
      </div>
      <span class="support-history-cta">View rolling 7-day history →</span>
    </a>
  </div>

  <div class="card chart-card sla-department-card">
    <div class="chart-title-row clean-title sla-history-heading">
      <div>
        <h2>SLA Breaches by Department — Rolling 7 Days</h2>
        <p><?= e(number_format($sevenDaySlaTotal)) ?> breached work order<?= $sevenDaySlaTotal === 1 ? '' : 's' ?> entered <?= e($rollingWindowLabel) ?>.</p>
      </div>
    </div>
    <div class="chart-box"><canvas id="slaDepartmentChart"></canvas></div>
  </div>
</section>
<script>window.workforceDashboardData = <?= json_encode($workforceDashboardData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<?php admin_ui_footer(['https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js']); ?>
