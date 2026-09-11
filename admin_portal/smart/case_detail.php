<?php
/**
 * SmartOps Source: admin_portal/smart/case_detail.php
 * Purpose: Admin Portal / Smart: Case Detail server-side page, endpoint, or reusable module.
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

$caseId = trim((string)($_GET['case_id'] ?? ''));
if ($caseId === '') redirect_to('dashboard.php');


// =============================================================================
// SECTION: Database Queries and Data Preparation
// =============================================================================
$case = fetch_one(
    "SELECT
        c.case_id,
        c.room_id,
        c.issue_summary,
        c.issue,
        c.hotel_asset,
        c.component,
        c.severity,
        c.priority,
        sm.cleaned_comment,
        sm.ticket_status,
        sm.completed_date,
        sm.corrective_action,
        sm.verification,
        sm.technician_assigned,
        wc.work_stage,
        wc.task_status,
        wc.assigned_at,
        wc.accepted_at,
        wc.started_at,
        wc.completed_at,
        wc.technician_note,
        COALESCE(t.name, sm.technician_assigned, wc.assigned_technician_id, wc.technician_id, '-') AS technician_name
     FROM cases c
     LEFT JOIN smart_maintenance_tickets sm ON sm.case_id=c.case_id
     LEFT JOIN workforce_cases wc ON wc.case_id=c.case_id
     LEFT JOIN technicians t ON t.technician_id=COALESCE(NULLIF(wc.assigned_technician_id,''), NULLIF(wc.technician_id,''), NULLIF(sm.technician_assigned,''))
     WHERE c.case_id=?
     LIMIT 1",
    [$caseId]
);

if (!$case) {

    // =============================================================================
    // SECTION: Page Layout and Rendering
    // =============================================================================
    admin_ui_header('Maintenance Case', 'smart', 'Case not found.', true);
    ?>
    <!-- SECTION: Detail Content -->
    <section class="detail-page"><section class="chart-card"><h2>Case not found</h2><p>The requested maintenance case is no longer available.</p></section></section>
    <?php admin_ui_footer(); exit;
}

$status = trim((string)($case['ticket_status'] ?? $case['task_status'] ?? $case['work_stage'] ?? 'Pending'));
$completedAt = trim((string)($case['completed_date'] ?? $case['completed_at'] ?? ''));
$issue = trim((string)($case['cleaned_comment'] ?? $case['issue_summary'] ?? $case['issue'] ?? '-'));
$severityClass = strtolower(trim((string)($case['severity'] ?? '')));
$severityClass = in_array($severityClass, ['critical','high','medium','low'], true) ? $severityClass : 'low';
$statusClass = str_contains(strtolower($status), 'complete') ? 'completed' : (str_contains(strtolower($status), 'progress') ? 'progress' : (str_contains(strtolower($status), 'assign') ? 'assigned' : 'pending'));

admin_ui_header(
    'Completed Maintenance Case',
    'smart',
    'Notification opened from the Admin completion alert.',
    true
);
?>
<!-- SECTION: Detail Content -->
<section class="detail-page">
  <section class="detail-toolbar">
    <div>
      <h2><?= e($case['case_id']) ?> · Room <?= e($case['room_id'] ?: '-') ?></h2>
      <p>This record reflects the latest status shared by the technician workflow.</p>
    </div>
    <a class="secondary-btn" href="dashboard.php">Back to Smart Maintenance</a>
  </section>

  <!-- SECTION: KPI Cards -->
  <section class="kpi-container four-kpi detail-kpi-grid">
    <div class="kpi-card"><h3>Status</h3><h2><span class="status-badge status-<?= e($statusClass) ?>"><?= e($status ?: '-') ?></span></h2></div>
    <div class="kpi-card"><h3>Severity</h3><h2><span class="severity-badge severity-<?= e($severityClass) ?>"><?= e($case['severity'] ?: '-') ?></span></h2></div>
    <div class="kpi-card"><h3>Technician</h3><h2><?= e($case['technician_name'] ?: '-') ?></h2></div>
    <div class="kpi-card"><h3>Completed At</h3><h2 style="font-size:18px"><?= e($completedAt ?: '-') ?></h2></div>
  </section>

  <!-- SECTION: Charts / Analytics -->
  <section class="chart-card">
    <div class="table-header"><div><h2>Case Completion Details</h2><p>Operational information synchronized from the technician task lifecycle.</p></div></div>
    <div class="case-detail-grid">
      <div class="case-detail-item"><span>Case ID</span><strong><?= e($case['case_id']) ?></strong></div>
      <div class="case-detail-item"><span>Room</span><strong><?= e($case['room_id'] ?: '-') ?></strong></div>
      <div class="case-detail-item"><span>Hotel Asset</span><strong><?= e(asset_display_name($case['hotel_asset'] ?? '')) ?></strong></div>
      <div class="case-detail-item"><span>Component</span><strong><?= e(component_display_name($case['component'] ?? '', $case['hotel_asset'] ?? '')) ?></strong></div>
      <div class="case-detail-item full"><span>Complaint / Issue</span><strong><?= e($issue) ?></strong></div>
      <div class="case-detail-item"><span>Assigned At</span><strong><?= e($case['assigned_at'] ?: '-') ?></strong></div>
      <div class="case-detail-item"><span>Started At</span><strong><?= e($case['started_at'] ?: '-') ?></strong></div>
      <div class="case-detail-item"><span>Completed At</span><strong><?= e($completedAt ?: '-') ?></strong></div>
      <div class="case-detail-item"><span>Priority</span><strong><?= e($case['priority'] ?: '-') ?></strong></div>
      <div class="case-detail-item full"><span>Technician Completion Note</span><strong><?= e($case['technician_note'] ?: 'Repair completed.') ?></strong></div>
      <?php if (trim((string)($case['verification'] ?? '')) !== ''): ?>
        <div class="case-detail-item full"><span>Verification</span><strong><?= e($case['verification']) ?></strong></div>
      <?php endif; ?>
    </div>
  </section>
</section>
<?php admin_ui_footer(); ?>
