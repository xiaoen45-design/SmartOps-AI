<?php
/**
 * SmartOps Source: admin_portal/hitl/pending_review.php
 * Purpose: Admin Portal / HITL: Pending Review server-side page, endpoint, or reusable module.
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

$sourceDashboard = strtolower(trim((string)($_GET['source'] ?? 'hitl')));
$backDashboard = $sourceDashboard === 'overview'
    ? 'admin_portal/overview/dashboard.php'
    : 'admin_portal/hitl/dashboard.php';

// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
admin_ui_header('Pending Review Case Summary', 'hitl', 'Review pending HITL cases requiring manager attention.', true, $backDashboard);

$dbMessage = database_ready_message();
if ($dbMessage !== '') { ?>
<!-- SECTION: Content Card -->
<section class="card"><h2>Database is not ready</h2><p><?= e($dbMessage) ?></p></section>
<?php admin_ui_footer(); exit; }

if (($_GET['capacity_error'] ?? '') === '1') { ?>
  <div class="support-error-message" role="alert">The selected HITL Technician already has an active task. Please choose another technician with available capacity.</div>
<?php }


// =============================================================================
// SECTION: Database Queries and Data Preparation
// =============================================================================
$rows = fetch_all("SELECT h.*, s.ai_confidence
                   FROM hitl_cases h
                   LEFT JOIN smart_maintenance_tickets s ON s.case_id = h.case_id
                   WHERE h.review_status IS NULL OR h.review_status = '' OR h.review_status LIKE '%Pending%' OR h.review_status LIKE '%Review%'
                   ORDER BY h.id DESC");
$technicians = fetch_assignable_technicians('Technical Specialist');

$criticalCount = count(array_filter($rows, fn(array $row): bool => hitl_primary_escalation_trigger($row) === 'Critical'));
$highCount = count(array_filter($rows, fn(array $row): bool => hitl_primary_escalation_trigger($row) === 'High'));
$lowConfidenceCount = count(array_filter($rows, fn(array $row): bool => hitl_primary_escalation_trigger($row) === 'Low Confidence'));
$outOfKbRows = array_values(array_filter($rows, 'hitl_is_out_of_knowledge_case'));
$outOfKbCaseIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['case_id'] ?? ''), $outOfKbRows), true);
$outOfKbCount = count($outOfKbRows);
?>
<!-- SECTION: KPI Cards -->
<section class="kpis four pending-review-kpis filter-kpis" aria-label="Pending review filters">
  <button type="button" class="kpi filter-kpi" data-case-filter="critical"><h3>Immediate</h3><h2><?= e(number_format($criticalCount)) ?></h2></button>
  <button type="button" class="kpi filter-kpi" data-case-filter="high"><h3>Urgent</h3><h2><?= e(number_format($highCount)) ?></h2></button>
  <button type="button" class="kpi filter-kpi" data-case-filter="low-confidence"><h3>Low Confidence</h3><h2><?= e(number_format($lowConfidenceCount)) ?></h2></button>
  <button type="button" class="kpi filter-kpi" data-case-filter="out-of-kb"><h3>Out of Knowledge Base</h3><h2><?= e(number_format($outOfKbCount)) ?></h2></button>
</section>

<!-- SECTION: Data Table -->
<section class="card pending-review-table-card">
  <div class="table-header">
    <div><h2>Pending Review Cases</h2><p id="pendingReviewCount"><?= e(number_format(count($rows))) ?> case(s) waiting for manager review.</p></div>
  </div>
  <div class="table-box pending-review-table-box">
    <!-- SECTION: Data Table -->
    <table class="dashboard-table" id="pendingReviewTable">
      <thead><tr><th>Case ID</th><th>Room</th><th>Hotel Asset</th><th>Component</th><th>Severity</th><th>Priority</th><th>Review Reason</th><th>Review Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row):
          $caseId = (string)($row['case_id'] ?? '');
          $primaryTrigger = hitl_primary_escalation_trigger($row);
          $flags = [];
          if ($primaryTrigger === 'Critical') $flags[] = 'critical';
          if ($primaryTrigger === 'High') $flags[] = 'high';
          if ($primaryTrigger === 'Low Confidence') $flags[] = 'low-confidence';
          if (isset($outOfKbCaseIds[$caseId])) $flags[] = 'out-of-kb';
          $detail = [
              'caseId'=>$caseId,'room'=>(string)($row['room']??'-'),'asset'=>asset_display_name($row['hotel_asset']??'Unknown'),
              'assetRaw'=>(string)($row['hotel_asset']??''),'component'=>(string)($row['component']??'-'),
              'issue'=>(string)($row['issue'] ?: $row['issue_summary'] ?: '-'),'failureMode'=>failure_mode_display_name($row['failure_mode']??'-', $row['component']??'', $row['hotel_asset']??''),
              'safety'=>(string)($row['safety_flag']??'-'),'severity'=>(string)($row['severity']??'-'),
              'priority'=>priority_display_name($row['priority']??''),'trigger'=>$primaryTrigger,'reason'=>(string)($row['hitl_reason']??'-'),'confidence'=>(string)($row['ai_confidence']??'-'),'isRestricted'=>hitl_is_out_of_knowledge_case($row),'isOokb'=>hitl_is_out_of_knowledge_case($row)
          ];
      ?>
        <tr data-case-types="<?= e(implode(' ', $flags)) ?>">
          <td><?= e($caseId) ?></td><td><?= e($row['room'] ?: '-') ?></td>
          <td><?= e(asset_display_name($row['hotel_asset'] ?? 'Unknown')) ?></td><td><?= e(component_display_name($row['component'] ?? '-', $row['hotel_asset'] ?? '')) ?></td>
          <td><?= severity_badge_html($row['severity'] ?? '') ?></td><td><?= e(priority_display_name($row['priority'] ?? '')) ?></td><td><?= e(trim((string)($row['hitl_reason'] ?? '')) !== '' ? (string)$row['hitl_reason'] : $primaryTrigger) ?></td>
          <td><?= e($row['review_status'] ?: 'Pending Review') ?></td>
          <td><button type="button" class="review-btn" data-review='<?= e(json_encode($detail, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?>'>Review</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php include __DIR__ . '/review_modal.php'; ?>
<script>window.hitlTechnicians = <?= json_encode($technicians, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;</script>
<?php admin_ui_footer(); ?>
