<?php
/**
 * SmartOps Source: admin_portal/hitl/trigger_detail.php
 * Purpose: Admin Portal / HITL: Trigger Detail server-side page, endpoint, or reusable module.
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
$trigger = trim((string)($_GET['trigger'] ?? ''));
$allowed = ['Critical', 'High', 'Low Confidence', 'Out of Knowledge Base'];
if (!in_array($trigger, $allowed, true)) $trigger = 'Critical';

$isOutOfKnowledgeBase = $trigger === 'Out of Knowledge Base';
$isRestrictedDetail = $isOutOfKnowledgeBase;
$titleLabels = [
    'Critical' => 'Immediate Priority',
    'High' => 'Urgent Priority',
    'Low Confidence' => 'Low Confidence',
    'Out of Knowledge Base' => 'Out-of-Knowledge-Base',
];


// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
admin_ui_header(
    ($titleLabels[$trigger] ?? $trigger) . ' HITL Case Summary',
    'hitl',
    $isRestrictedDetail
        ? 'Cases shown with restricted details because the AI result requires human review.'
        : 'Cases filtered by the selected HITL review queue.',
    true,
    $backDashboard
);


// =============================================================================
// SECTION: Database Queries and Data Preparation
// =============================================================================
$rows = fetch_all(
    "SELECT h.*, s.ai_confidence
     FROM hitl_cases h
     LEFT JOIN smart_maintenance_tickets s ON s.case_id=h.case_id
     WHERE h.review_status IS NULL
        OR h.review_status=''
        OR h.review_status LIKE '%Pending%'
        OR h.review_status LIKE '%Review%'
     ORDER BY h.id DESC"
);

$rows = array_values(array_filter(
    $rows,
    static fn(array $row): bool => hitl_primary_escalation_trigger($row) === $trigger
));

$technicians = fetch_assignable_technicians('Technical Specialist');
?>
<?php if (($_GET['capacity_error'] ?? '') === '1'): ?>
  <div class="support-error-message" role="alert">The selected HITL Technician already has an active task. Please choose another technician with available capacity.</div>
<?php endif; ?>

<?php
$safetyFlaggedCases = count(array_filter($rows, static function (array $row): bool {
    $value = strtolower(trim((string)($row['safety_flag'] ?? '')));
    return in_array($value, ['1', 'true', 'yes', 'flagged'], true);
}));
?>

<!-- SECTION: KPI Cards -->
<section class="kpis three reason-detail-kpis detail-summary-kpis">
  <div class="kpi"><h3>Total Filtered Cases</h3><h2><?= e(number_format(count($rows))) ?></h2></div>
  <div class="kpi"><h3>Pending Review</h3><h2><?= e(number_format(count($rows))) ?></h2></div>
  <?php if ($isRestrictedDetail): ?>
    <div class="kpi"><h3>Safety-Flagged Cases</h3><h2><?= e(number_format($safetyFlaggedCases)) ?></h2></div>
  <?php else: ?>
    <div class="kpi"><h3>Hotel Assets Affected</h3><h2><?= e(number_format(count(array_unique(array_map(fn($row) => asset_display_name($row['hotel_asset'] ?? ''), $rows))))) ?></h2></div>
  <?php endif; ?>
</section>

<!-- SECTION: Data Table -->
<section class="card pending-review-table-card">
  <div class="table-header">
    <div>
      <h2><?= e($titleLabels[$trigger] ?? $trigger) ?> Cases</h2>
      <p><?= e(
          $isOutOfKnowledgeBase
              ? 'Cases that require review because the issue is outside the knowledge base.'
              : ($trigger === 'Low Confidence'
                  ? 'Cases that require review because the AI result has insufficient confidence.'
                  : 'Cases filtered by the selected HITL review queue.')
      ) ?></p>
    </div>
  </div>

  <div class="table-box pending-review-table-box">
    <!-- SECTION: Data Table -->
    <table class="dashboard-table">
      <thead>
        <tr>
          <?php if ($isRestrictedDetail): ?>
            <th>Case ID</th><th>Room</th><th>Issue</th><th>HITL Reason</th><th>Review Status</th><th>Action</th>
          <?php else: ?>
            <th>Case ID</th><th>Room</th><th>Hotel Asset</th><th>Component</th><th>Severity</th><th>Priority</th><th>Review Status</th><th>Action</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row):
          $caseId = (string)($row['case_id'] ?? '');
          $issue = (string)($row['issue'] ?: $row['issue_summary'] ?: '-');
          $detail = [
              'caseId' => $caseId,
              'room' => (string)($row['room'] ?? '-'),
              'asset' => asset_display_name($row['hotel_asset'] ?? 'Other'),
              'assetRaw' => (string)($row['hotel_asset'] ?? ''),
              'component' => (string)($row['component'] ?? '-'),
              'issue' => $issue,
              'failureMode' => failure_mode_display_name($row['failure_mode'] ?? '-', $row['component'] ?? '', $row['hotel_asset'] ?? ''),
              'safety' => (string)($row['safety_flag'] ?? '-'),
              'severity' => (string)($row['severity'] ?? '-'),
              'priority' => priority_display_name($row['priority'] ?? ''),
              'trigger' => hitl_primary_escalation_trigger($row),
              'reason' => (string)($row['hitl_reason'] ?? $trigger),
              'confidence' => (string)($row['ai_confidence'] ?? '-'),
              'isRestricted' => $isRestrictedDetail,
              'isOokb' => $isOutOfKnowledgeBase,
          ];
      ?>
        <tr>
          <?php if ($isRestrictedDetail): ?>
            <td><?= e($caseId) ?></td>
            <td><?= e($row['room'] ?: '-') ?></td>
            <td><?= e($issue) ?></td>
            <td><?= e($trigger) ?></td>
            <td><?= e($row['review_status'] ?: 'Pending Review') ?></td>
            <td><button type="button" class="review-btn" data-review='<?= e(json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>Review</button></td>
          <?php else: ?>
            <td><?= e($caseId) ?></td>
            <td><?= e($row['room'] ?: '-') ?></td>
            <td><?= e(asset_display_name($row['hotel_asset'] ?? 'Other')) ?></td>
            <td><?= e(component_display_name($row['component'] ?? '-', $row['hotel_asset'] ?? '')) ?></td>
            <td><?= severity_badge_html($row['severity'] ?? '') ?></td>
            <td><?= e(priority_display_name($row['priority'] ?? '')) ?></td>
            <td><?= e($row['review_status'] ?: 'Pending Review') ?></td>
            <td><button type="button" class="review-btn" data-review='<?= e(json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>Review</button></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php include __DIR__ . '/review_modal.php'; ?>
<script>window.hitlTechnicians = <?= json_encode($technicians, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<?php admin_ui_footer(); ?>
