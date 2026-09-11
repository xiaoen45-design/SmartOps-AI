<?php
/**
 * SmartOps Source: admin_portal/hitl/component_cases.php
 * Purpose: Admin Portal / HITL: Component Cases server-side page, endpoint, or reusable module.
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

$asset = trim((string)($_GET['asset'] ?? ''));
$component = trim((string)($_GET['component'] ?? ''));
if ($asset === '' || $component === '') redirect_to(app_url('admin_portal/hitl/dashboard.php'));


// =============================================================================
// SECTION: Page Layout and Rendering
// =============================================================================
admin_ui_header(
    component_display_name($component, $asset) . ' HITL Case Records',
    'hitl',
    'Pending HITL cases for the selected maintenance category and component.',
    true
);

$cleanAsset = clean_asset_name($asset);

// =============================================================================
// SECTION: Database Queries and Data Preparation
// =============================================================================
$rows = fetch_all(
    "SELECT h.*, s.ai_confidence
     FROM hitl_cases h
     LEFT JOIN smart_maintenance_tickets s ON s.case_id=h.case_id
     WHERE (h.review_status IS NULL OR h.review_status='' OR h.review_status LIKE '%Pending%' OR h.review_status LIKE '%Review%')
       AND h.component=?
       AND (h.hotel_asset=? OR REPLACE(h.hotel_asset,'D30 ','')=? OR REPLACE(h.hotel_asset,'D20 ','')=? OR REPLACE(h.hotel_asset,'D50 ','')=? OR REPLACE(h.hotel_asset,'D40 ','')=? OR REPLACE(h.hotel_asset,'D10 ','')=?)
     ORDER BY h.id DESC",
    [$component, $asset, $cleanAsset, $cleanAsset, $cleanAsset, $cleanAsset, $cleanAsset]
);
$technicians = fetch_assignable_technicians('Technical Specialist');
?>
<?php if (($_GET['capacity_error'] ?? '') === '1'): ?>
  <div class="support-error-message" role="alert">The selected HITL Technician already has an active task. Please choose another technician with available capacity.</div>
<?php endif; ?>

<!-- SECTION: Data Table -->
<section class="card pending-review-table-card">
  <div class="table-header">
    <div><h2>Tailored HITL Cases</h2><p><?= e(number_format(count($rows))) ?> case(s) for <?= e(component_display_name($component, $asset)) ?>.</p></div>
  </div>
  <div class="table-box pending-review-table-box">
    <!-- SECTION: Data Table -->
    <table class="dashboard-table">
      <thead><tr><th>Case ID</th><th>Room</th><th>Issue</th><th>Severity</th><th>Priority</th><th>Review Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row):
          $caseId = (string)($row['case_id'] ?? '');
          $detail = [
              'caseId' => $caseId,
              'room' => (string)($row['room'] ?? '-'),
              'asset' => asset_display_name($row['hotel_asset'] ?? 'Other'),
              'assetRaw' => (string)($row['hotel_asset'] ?? ''),
              'component' => (string)($row['component'] ?? '-'),
              'issue' => (string)($row['issue'] ?: $row['issue_summary'] ?: '-'),
              'failureMode' => failure_mode_display_name($row['failure_mode'] ?? '-', $row['component'] ?? '', $row['hotel_asset'] ?? ''),
              'safety' => (string)($row['safety_flag'] ?? '-'),
              'severity' => (string)($row['severity'] ?? '-'),
              'priority' => priority_display_name($row['priority'] ?? ''),
              'trigger' => hitl_primary_escalation_trigger($row),
              'confidence' => (string)($row['ai_confidence'] ?? '-'),
              'isRestricted' => hitl_is_restricted_detail_case($row),
              'isOokb' => hitl_is_out_of_knowledge_case($row),
          ];
      ?>
        <tr>
          <td><?= e($caseId) ?></td>
          <td><?= e($row['room'] ?: '-') ?></td>
          <td><?= e($row['issue'] ?: $row['issue_summary'] ?: '-') ?></td>
          <td><?= severity_badge_html($row['severity'] ?? '') ?></td>
          <td><?= e(priority_display_name($row['priority'] ?? '')) ?></td>
          <td><?= e($row['review_status'] ?: 'Pending Review') ?></td>
          <td><button type="button" class="review-btn" data-review='<?= e(json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>View Details</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php include __DIR__ . '/review_modal.php'; ?>
<script>window.hitlTechnicians = <?= json_encode($technicians, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<?php admin_ui_footer(); ?>
