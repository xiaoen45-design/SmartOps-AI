<?php
/**
 * SmartOps Source: technician_website/home.php
 * Purpose: Technician task workspace for Assigned, In Progress, Support, and Overdue work orders.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/support_config.php';


// =============================================================================
// SECTION: Authentication and Access Control
// =============================================================================
$techId = require_technician();
refresh_live_sla_statuses();
refresh_technician_summary($techId);
$tech = current_technician();

$filter = strtolower(trim($_GET['filter'] ?? 'assigned'));
$allowedFilters = ['assigned', 'progress', 'support', 'overdue'];
if (!in_array($filter, $allowedFilters, true)) $filter = 'assigned';

$filterLabels = [
    'assigned' => 'Assigned Tasks',
    'progress' => 'In Progress Tasks',
    'support' => 'Support Requests',
    'overdue' => 'Overdue Tasks',
];

$where = '';
$order = "ORDER BY CASE WHEN tt.severity='Critical' OR tt.priority='Immediate' THEN 1 WHEN tt.severity='High' OR tt.priority='Urgent' THEN 2 WHEN tt.severity='Medium' OR tt.priority='Standard' THEN 3 ELSE 4 END, tt.case_id LIMIT 80";

if ($filter === 'assigned') {
    $where = "tt.task_status IN ('Assigned','Waiting Technician Acceptance','Accepted')";
} elseif ($filter === 'progress') {
    $where = "tt.task_status IN ('In Progress','Repair In Progress','Started')";
} elseif ($filter === 'overdue') {
    $where = "tt.task_status NOT IN ('Completed','Waiting for Parts','Pending Senior Assignment','Pending Outsourcing','Outsourced') AND tt.sla_status IN ('Response Breach','Repair Breach','Breach','SLA Violation')";
}

if ($filter === 'support') {

    // =============================================================================
    // SECTION: Database Queries and Data Preparation
    // =============================================================================
    $rows = fetch_all(
        "SELECT tt.*, hc.case_id AS hitl_case_id, hc.hitl_reason, sm.ai_confidence
         FROM technician_tasks tt
         LEFT JOIN hitl_cases hc ON hc.case_id=tt.case_id
         LEFT JOIN smart_maintenance_tickets sm ON sm.case_id=tt.case_id
         WHERE tt.previous_technician_id=?
           AND tt.task_status IN ('Waiting for Parts','Pending Senior Assignment','Pending Outsourcing','Outsourced','Pending Reassignment') {$order}",
        [$techId]
    );
} else {
    $rows = fetch_all(
        "SELECT tt.*, hc.case_id AS hitl_case_id, hc.hitl_reason, sm.ai_confidence
         FROM technician_tasks tt
         LEFT JOIN hitl_cases hc ON hc.case_id=tt.case_id
         LEFT JOIN smart_maintenance_tickets sm ON sm.case_id=tt.case_id
         WHERE tt.technician_id=? AND {$where} {$order}",
        [$techId]
    );
}


// =============================================================================
// SECTION: Tech Action Button
// =============================================================================
function tech_action_button(string $label, string $action, array $task, string $extraClass = ''): string {
    $caseId = e($task['case_id']);
    $room = e($task['room']);
    $issue = e(technician_issue_title($task));
    $hotelAsset = e(asset_display_name($task['hotel_asset'] ?? ''));
    $classes = trim('btn tech-action-trigger ' . $extraClass);

    return '<button type="button" class="' . $classes . '"'
        . ' data-action="' . e($action) . '"'
        . ' data-case-id="' . $caseId . '"'
        . ' data-room="' . $room . '"'
        . ' data-issue="' . $issue . '"'
        . ' data-hotel-asset="' . $hotelAsset . '"'
        . '>' . e($label) . '</button>';
}


// =============================================================================
// SECTION: Tech Direct Action Button
// =============================================================================
function tech_direct_action_button(string $label, string $action, array $task, string $extraClass = ''): string {
    $classes = trim('btn ' . $extraClass);
    return '<form class="inline-task-action" method="post" action="task_action.php">'
        . '<input type="hidden" name="case_id" value="' . e($task['case_id']) . '">'
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<button type="submit" class="' . e($classes) . '">' . e($label) . '</button>'
        . '</form>';
}


// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
tech_header('My Work', $filter);
?>
<div class="tech-section-head tech-task-list-heading">
  <h2><?= e($filterLabels[$filter]) ?></h2>
  <span class="tech-task-count"><?= e(number_format(count($rows))) ?></span>
</div>

<?php foreach ($rows as $r): ?>
  <div class="task-card <?= is_sla_breach($r) ? 'overdue' : '' ?>">
    <div class="task-card-head"><div><span class="task-card-eyebrow">Work Order</span><h3><?= e($r['case_id']) ?></h3></div></div>
    <?= technician_task_info_grid($r, $filter === 'support' || $filter === 'overdue') ?>

    <?php if ($filter === 'support'): ?>
      <div class="notice compact-notice">This case has been released from your active workload. Track the manager action here.</div>
    <?php endif; ?>

    <div class="btn-row task-actions">
      <?php if (in_array($r['task_status'], ['Assigned', 'Waiting Technician Acceptance'], true)): ?>
        <?= tech_direct_action_button('Accept', 'accept', $r) ?>
      <?php endif; ?>

      <?php if (in_array($r['task_status'], ['Accepted'], true)): ?>
        <?= tech_action_button('Start Repair', 'start', $r) ?>
      <?php endif; ?>

      <?php if (in_array($r['task_status'], ['In Progress', 'Repair In Progress', 'Started'], true)): ?>
        <?= tech_action_button('Complete Repair', 'complete', $r) ?>
        <?= tech_action_button('Request Support', 'support', $r, 'btn-outline') ?>
      <?php endif; ?>
    </div>

    <?php if ($filter !== 'support'): ?><?= technician_guidance_panel($r, false, false) ?><?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if (!$rows): ?>
  <div class="card notice">No <?= e(strtolower($filterLabels[$filter])) ?> found.</div>
<?php endif; ?>

<div class="tech-modal-backdrop" id="techActionModal" hidden>
  <div class="tech-modal-card" role="dialog" aria-modal="true" aria-labelledby="techActionTitle">
    <!-- SECTION: Form -->
    <form method="post" action="task_action.php" id="techActionForm">
      <input type="hidden" name="case_id" id="modalCaseId">
      <input type="hidden" name="action" id="modalAction">

      <div class="tech-modal-head">
        <div>
          <p class="muted modal-eyebrow" id="modalCaseLabel">CASE</p>
          <h2 id="techActionTitle">Confirm Action</h2>
        </div>
        <button type="button" class="modal-x" id="modalCloseBtn" aria-label="Close">×</button>
      </div>

      <div class="modal-task-box">
        <strong id="modalRoomText">Room</strong>
        <p id="modalIssueText">Issue</p>
      </div>

      <p class="modal-message" id="modalMessage">Please confirm this action.</p>

      <div class="support-choice-row" id="supportReasonRow" hidden>
        <p class="support-choice-title">Select support reason</p>
        <label class="support-choice-card" data-support-option="parts">
          <input type="radio" name="support_reason" value="Parts Required">
          <span>Parts Required</span>
        </label>
        <?php if (technician_level_label($tech) === 'Junior'): ?>
        <label class="support-choice-card" data-support-option="senior">
          <input type="radio" name="support_reason" value="Senior Support">
          <span>Senior Support</span>
        </label>
        <?php endif; ?>
        <label class="support-choice-card" data-support-option="outsourcing">
          <input type="radio" name="support_reason" value="Outsourcing">
          <span>Outsourcing</span>
        </label>

        <div class="support-detail-panel" id="partsDetailPanel" hidden>
          <label for="partSelect">Required part</label>
          <select class="form-control" name="part_name" id="partSelect">
            <option value="">Select a part</option>
          </select>
          <label for="otherPartInput" id="otherPartLabel" hidden>Other part</label>
          <input class="form-control" type="text" name="other_part" id="otherPartInput" placeholder="Enter the required part" hidden>
          <label for="partQuantity">Quantity</label>
          <input class="form-control" type="number" name="part_quantity" id="partQuantity" min="1" value="1" inputmode="numeric">
          <label for="partRemark">Remark <span class="optional-label">(Optional)</span></label>
          <textarea class="form-control" name="part_remark" id="partRemark" rows="2" placeholder="Add a short note only when needed"></textarea>
        </div>

        <div class="support-detail-panel" id="seniorDetailPanel" hidden>
          <label for="seniorReasonSelect">Reason for senior support</label>
          <select class="form-control" name="senior_reason" id="seniorReasonSelect">
            <option value="">Select a reason</option>
          </select>
          <label for="seniorOtherReason" id="seniorOtherReasonLabel" hidden>Other reason</label>
          <input class="form-control" type="text" name="senior_other_reason" id="seniorOtherReason" placeholder="Enter the reason" hidden>
        </div>

        <div class="support-detail-panel" id="outsourcingDetailPanel" hidden>
          <label for="outsourcingServiceSelect">Required external service</label>
          <select class="form-control" name="outsourcing_service" id="outsourcingServiceSelect">
            <option value="">Select an external service</option>
          </select>
          <label for="outsourcingOtherService" id="outsourcingOtherServiceLabel" hidden>Other external service</label>
          <input class="form-control" type="text" name="outsourcing_other_service" id="outsourcingOtherService" placeholder="Enter the external service" hidden>
          <label for="outsourcingReasonSelect">Reason for outsourcing</label>
          <select class="form-control" name="outsourcing_reason" id="outsourcingReasonSelect">
            <option value="">Select a reason</option>
          </select>
          <label for="outsourcingOtherReason" id="outsourcingOtherReasonLabel" hidden>Other reason</label>
          <input class="form-control" type="text" name="outsourcing_other_reason" id="outsourcingOtherReason" placeholder="Enter the reason" hidden>
        </div>
      </div>

      <div class="btn-row modal-actions">
        <button type="submit" class="btn" id="modalSubmitBtn">Confirm</button>
        <button type="button" class="btn btn-outline" id="modalCancelBtn">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>window.TECH_SUPPORT_CONFIG = <?= json_encode(technician_support_config(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<?php tech_footer(); ?>
