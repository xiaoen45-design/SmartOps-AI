<?php
/**
 * SmartOps Source: includes/technician_layout.php
 * Purpose: Shared Technician mobile layout, task display helpers, phone frame, navigation drawer, and page shell.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/helpers.php';
ensure_hitl_dataset_consistency();
ensure_technician_task_trigger_column();


// =============================================================================
// SECTION: Technician Asset URL
// =============================================================================
function technician_asset_url(string $type): string {
    $filename = $type === 'css' ? 'technician.css' : 'technician.js';
    return app_url('technician_website/assets/' . $type . '/' . $filename);
}


// =============================================================================
// SECTION: Technician Asset Version
// =============================================================================
function technician_asset_version(string $type): string {
    $filename = $type === 'css' ? 'technician.css' : 'technician.js';
    return asset_file_version('technician_website/assets/' . $type . '/' . $filename);
}


// =============================================================================
// SECTION: Technician Task Reason Text
// =============================================================================
function technician_task_reason_text(array $task): string {
    foreach ([
        $task['history_note'] ?? '',
        $task['support_reason'] ?? '',
        $task['technician_sla_reason'] ?? '',
        $task['technician_note'] ?? '',
        $task['sla_reason'] ?? '',
        $task['sla_cause'] ?? '',
    ] as $candidate) {
        $reason = trim((string)$candidate);
        if ($reason === '') continue;
        $reason = str_replace(';', '.', $reason);
        return preg_replace('/\s+/', ' ', $reason);
    }
    return '';
}


// =============================================================================
// SECTION: Technician Should Show Reason
// =============================================================================
function technician_should_show_reason(array $task, bool $force = false): bool {
    if ($force) return technician_task_reason_text($task) !== '';

    $status = strtolower((string)($task['task_status'] ?? ''));
    $stage = strtolower((string)($task['work_stage'] ?? ''));
    $reason = strtolower(technician_task_reason_text($task));

    return str_contains($status, 'support')
        || str_contains($stage, 'support')
        || str_contains($reason, 'support')
        || str_contains($reason, 'parts')
        || str_contains($reason, 'outsourcing')
        || str_contains($reason, 'sla')
        || str_contains($reason, 'breach');
}


// =============================================================================
// SECTION: Technician Status Label
// =============================================================================
function technician_status_label(?string $status): string {
    $value = trim((string)$status);
    if ($value === '') return '-';

    return match (strtolower($value)) {
        'repair in progress', 'started' => 'In Progress',
        'waiting technician acceptance' => 'Assigned',
        'pending senior assignment' => 'Pending Senior Assignment',
        'waiting for parts' => 'Waiting for Parts',
        'pending outsourcing' => 'Pending Outsourcing',
        default => $value,
    };
}


// =============================================================================
// SECTION: Technician Task Info Grid
// =============================================================================
function technician_task_info_grid(array $task, bool $forceReason = false): string {
    $issue = technician_issue_title($task);
    $reason = technician_task_reason_text($task);
    $isRestricted = technician_is_restricted_guidance_task($task);
    $hitlReason = trim((string)($task['hitl_reason'] ?? $task['escalation_trigger'] ?? ''));
    $status = technician_status_label($task['task_status'] ?? '');
    $room = trim((string)($task['room'] ?? '')) ?: 'Not provided';

    $html = '<div class="task-overview">';

    // Keep the most important task facts together in one clean summary strip.
    $html .= '<div class="task-summary-strip">';
    if ($isRestricted) {
        $reviewReason = $hitlReason !== '' ? $hitlReason : 'Human Review Required';
        $html .= '<div class="task-summary-cell task-summary-wide"><span>HITL Reason</span><strong>'.e($reviewReason).'</strong></div>';
    } else {
        $severity = trim((string)($task['severity'] ?? '')) ?: '-';
        $priority = priority_display_name($task['priority'] ?? '');
        $html .= '<div class="task-summary-cell"><span>Severity</span><strong class="'.e(priority_class($severity)).'">'.e($severity).'</strong></div>'
            . '<div class="task-summary-cell"><span>Priority</span><strong class="'.e(priority_class($priority)).'">'.e($priority).'</strong></div>';
    }
    $html .= '<div class="task-summary-cell"><span>Status</span><strong class="'.e(stage_class($status)).'">'.e($status).'</strong></div>'
        . '</div>';

    // Flat detail rows are easier to scan on a phone than a grid of separate boxes.
    $html .= '<dl class="task-detail-list">'
        . '<div class="task-detail-row"><dt>Room</dt><dd>'.e($room).'</dd></div>'
        . '<div class="task-detail-row"><dt>Assigned</dt><dd>'.e(display_task_time($task, 'assigned_time')).'</dd></div>'
        . '<div class="task-detail-row task-detail-text"><dt>Issue</dt><dd>'.e($issue).'</dd></div>';

    if ($reason !== '' && technician_should_show_reason($task, $forceReason)) {
        $html .= '<div class="task-detail-row task-detail-text"><dt>Reason</dt><dd>'.e($reason).'</dd></div>';
    }
    $html .= '</dl>';

    $timelineItems = [
        ['Accepted', display_task_time($task, 'accepted_time')],
        ['Repair Start', display_task_time($task, 'started_time')],
    ];

    $historyStatus = strtolower(trim((string)($task['history_status'] ?? $task['task_status'] ?? '')));
    if ($historyStatus === 'repair completed' || $historyStatus === 'completed') {
        $timelineItems[] = ['Completed', display_task_time($task, 'completed_time')];
    } elseif (trim((string)($task['completed_time'] ?? '')) !== '') {
        $timelineItems[] = ['Released', display_task_time($task, 'completed_time')];
    }

    $hasTimelineActivity = false;
    foreach ($timelineItems as [, $value]) {
        if (trim((string)$value) !== '' && trim((string)$value) !== '-') {
            $hasTimelineActivity = true;
            break;
        }
    }

    if ($hasTimelineActivity) {
        $html .= '<div class="task-timeline" aria-label="Task timeline">';
        foreach ($timelineItems as [$label, $value]) {
            $isDone = trim((string)$value) !== '' && trim((string)$value) !== '-';
            $html .= '<div class="task-timeline-step'.($isDone ? ' is-done' : '').'">'
                . '<span class="task-timeline-dot" aria-hidden="true"></span>'
                . '<span class="task-timeline-label">'.e($label).'</span>'
                . '<strong>'.e($value).'</strong>'
                . '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}


// =============================================================================
// SECTION: Technician Is Out of KB Task
// =============================================================================
function technician_is_ookb_task(array $task): bool {
    // Only a real HITL record can be treated as Out of Knowledge Base.
    // Normal AI Automation tasks must never lose their guidance because of
    // an unrelated or stale escalation_trigger value.
    $storedReason = trim((string)($task['hitl_reason'] ?? ''));
    $hasHitlRecord = trim((string)($task['hitl_case_id'] ?? '')) !== '' || $storedReason !== '';
    if (!$hasHitlRecord) return false;

    if ($storedReason !== '') {
        return str_contains(strtolower($storedReason), 'out of knowledge');
    }

    $storedTrigger = trim((string)($task['escalation_trigger'] ?? ''));
    return strcasecmp($storedTrigger, 'Out of Knowledge Base') === 0;
}


// =============================================================================
// SECTION: Technician Is Restricted Guidance Task
// =============================================================================
function technician_is_restricted_guidance_task(array $task): bool {
    $storedReason = trim((string)($task['hitl_reason'] ?? ''));
    $storedTrigger = trim((string)($task['escalation_trigger'] ?? ''));
    $reason = strtolower($storedReason !== '' ? $storedReason : $storedTrigger);

    $hasHitlRecord = trim((string)($task['hitl_case_id'] ?? '')) !== '' || $storedReason !== '';
    if (!$hasHitlRecord) return false;

    return str_contains($reason, 'out of knowledge')
        || str_contains($reason, 'low confidence');
}


// =============================================================================
// SECTION: Technician Guidance Panel
// =============================================================================
function technician_guidance_panel(array $task, bool $showPreventive, bool $open = false): string {
    if (technician_is_restricted_guidance_task($task)) return '';
    $corrective = trim((string)($task['corrective_action'] ?? ''));
    $verification = trim((string)($task['verification'] ?? ''));
    $preventive = trim((string)($task['preventive_maintenance'] ?? ''));

    $items = [];
    if ($corrective !== '') $items[] = '<div class="task-guidance-item"><small>Corrective Action</small><strong>'.e($corrective).'</strong></div>';
    if ($verification !== '') $items[] = '<div class="task-guidance-item"><small>Verification</small><strong>'.e($verification).'</strong></div>';
    if ($showPreventive && $preventive !== '') $items[] = '<div class="task-guidance-item"><small>Preventive Maintenance</small><strong>'.e($preventive).'</strong></div>';

    if (!$items) return '';

    return '<details class="task-guidance-panel"'.($open ? ' open' : '').'>'
        . '<summary class="task-guidance-heading"><span>Repair Guidance</span><span class="task-guidance-chevron" aria-hidden="true"></span></summary>'
        . '<div class="task-guidance-content">'.implode('', $items).'</div>'
        . '</details>';
}


// =============================================================================
// SECTION: Tech Header
// =============================================================================
function tech_header(string $title, string $active = ''): void {
    $isWorkPage = in_array($active, ['assigned','progress','support','overdue'], true);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta http-equiv="Cache-Control" content="no-store">
  <title><?= e($title) ?> - SmartOps Technician</title>
  <link rel="stylesheet" href="<?= e(technician_asset_url('css')) ?>?v=<?= e(technician_asset_version('css')) ?>">
</head>
<body class="tech-body<?= $isWorkPage ? ' tech-work-body' : '' ?>">
  <div class="device-stage">
    <div class="phone-frame" aria-label="Mobile website preview frame">
      <span class="phone-side-button phone-side-button--volume" aria-hidden="true"></span>
      <span class="phone-side-button phone-side-button--power" aria-hidden="true"></span>
      <span class="phone-top-cutout" aria-hidden="true"></span>
      <div class="phone-screen">
        <main class="phone-shell">
    <!-- SECTION: Page Header -->
    <header class="tech-top mobile-app-header">
      <a class="brand" href="<?= e(app_url('technician_website/home.php?filter=assigned')) ?>" aria-label="SmartOps Technician Portal home">
        <span class="brand-name"><span>Smart</span><strong>Ops</strong></span>
        <small>Technician Portal</small>
      </a>
      <button type="button" class="tech-menu-toggle" id="techMenuToggle" aria-expanded="false" aria-controls="techMenuDrawer" aria-label="Open navigation menu">
        <span></span><span></span><span></span>
      </button>
    </header>

    <div class="tech-menu-backdrop" id="techMenuBackdrop" hidden></div>
    <!-- SECTION: Mobile Drawer Navigation -->
    <aside class="tech-menu-drawer" id="techMenuDrawer" aria-hidden="true">
      <div class="tech-menu-head">
        <div>
          <strong>SmartOps</strong>
          <span>Technician Portal</span>
        </div>
        <button type="button" class="tech-menu-close" id="techMenuClose" aria-label="Close navigation menu">×</button>
      </div>
      <!-- SECTION: Technician Navigation Links -->
      <nav class="tech-menu-links" aria-label="Technician navigation">
        <a class="<?= $isWorkPage ? 'active' : '' ?>" href="<?= e(app_url('technician_website/home.php?filter=assigned')) ?>"><span>My Work</span><small>Assigned and active tasks</small></a>
        <a class="<?= $active === 'history' ? 'active' : '' ?>" href="<?= e(app_url('technician_website/history.php')) ?>"><span>History</span><small>Completed and released tasks</small></a>
        <a class="<?= in_array($active, ['account','profile','settings'], true) ? 'active' : '' ?>" href="<?= e(app_url('technician_website/settings.php')) ?>"><span>Account</span><small>Profile, contact and security</small></a>
        <a class="danger" href="<?= e(app_url('technician_website/logout.php')) ?>"><span>Logout</span><small>Sign out of the portal</small></a>
      </nav>
    </aside>

    <?php if (!$isWorkPage): ?>
    <section class="tech-page-heading">
      <div><h1><?= e($title) ?></h1></div>
      <?php if ($active === 'history'): ?>
        <a class="btn btn-outline tech-heading-action" href="<?= e(app_url('technician_website/home.php?filter=assigned')) ?>">Back to My Work</a>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($isWorkPage): ?>
      <!-- SECTION: Work Status Tabs -->
      <nav class="tech-tabs tech-tabs-sticky" aria-label="Work task filters">
        <a class="<?= $active==='assigned'?'active':'' ?>" href="<?= e(app_url('technician_website/home.php?filter=assigned')) ?>">Assigned</a>
        <a class="<?= $active==='progress'?'active':'' ?>" href="<?= e(app_url('technician_website/home.php?filter=progress')) ?>">In Progress</a>
        <a class="<?= $active==='support'?'active':'' ?>" href="<?= e(app_url('technician_website/home.php?filter=support')) ?>">Support</a>
        <a class="<?= $active==='overdue'?'active':'' ?>" href="<?= e(app_url('technician_website/home.php?filter=overdue')) ?>">Overdue</a>
      </nav>
    <?php endif; ?>
<?php }


// =============================================================================
// SECTION: Tech Footer
// =============================================================================
function tech_footer(): void { ?>
        </main>
      </div>
    </div>
  </div>
  <script src="<?= e(technician_asset_url('js')) ?>?v=<?= e(technician_asset_version('js')) ?>"></script>
</body>
</html>
<?php }
