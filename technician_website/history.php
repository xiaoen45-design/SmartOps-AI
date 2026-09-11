<?php
/**
 * SmartOps Source: technician_website/history.php
 * Purpose: Technician completed and released task history.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/auth.php';


// =============================================================================
// SECTION: Authentication and Access Control
// =============================================================================
$techId = require_technician();
refresh_live_sla_statuses();
refresh_technician_summary($techId);
ensure_workflow_support_columns();
ensure_completed_assignment_history_for_technician($techId);

// =============================================================================
// SECTION: Database Queries and Data Preparation
// =============================================================================
$rows = fetch_all(
    "SELECT tt.*, hc.case_id AS hitl_case_id, hc.hitl_reason, sm.ai_confidence,
            ah.end_status AS history_status, ah.event_note AS history_note,
            ah.assigned_at AS assigned_time, ah.accepted_at AS accepted_time,
            ah.started_at AS started_time, ah.ended_at AS completed_time,
            ah.end_status AS task_status
     FROM task_assignment_history ah
     INNER JOIN technician_tasks tt ON tt.case_id=ah.case_id
     LEFT JOIN hitl_cases hc ON hc.case_id=tt.case_id
     LEFT JOIN smart_maintenance_tickets sm ON sm.case_id=tt.case_id
     WHERE ah.technician_id=? AND ah.ended_at IS NOT NULL
     ORDER BY ah.ended_at DESC, ah.id DESC LIMIT 100",
    [$techId]
);


// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
tech_header('History', 'history');
?>
<?php foreach ($rows as $task): ?>
  <article class="task-card completed">
    <div class="task-card-head"><div><span class="task-card-eyebrow">Work Order</span><h3><?= e($task['case_id']) ?></h3></div></div>
    <?= technician_task_info_grid($task, true) ?>
    <?php if (($task['history_status'] ?? '') === 'Repair Completed'): ?>
      <?= technician_guidance_panel($task, true) ?>
    <?php else: ?>
      <div class="notice compact-notice"><?= e($task['history_status'] ?? 'Released') ?><?= trim((string)($task['history_note'] ?? '')) !== '' ? ' — ' . e($task['history_note']) : '' ?></div>
    <?php endif; ?>
  </article>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="card notice">No task history.</div><?php endif; ?>
<?php tech_footer(); ?>
