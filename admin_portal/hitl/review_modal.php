<!--
  SmartOps Source: admin_portal/hitl/review_modal.php
  Purpose: Reusable HITL case review and technician assignment modal.
-->
<!-- SECTION: HITL Review Modal -->
<div class="review-modal" id="reviewModal" aria-hidden="true">
  <div class="review-modal-card" role="dialog" aria-modal="true" aria-labelledby="reviewModalTitle">
    <button class="review-modal-close" type="button" aria-label="Close">×</button>
    <h2 id="reviewModalTitle">HITL Case Review</h2>
    <div class="review-detail-grid" id="reviewDetailGrid"></div>

    <!-- SECTION: Technician Assignment Form -->
    <form class="review-assignment-form" id="reviewAssignmentForm" method="post" action="<?= e(app_url('admin_portal/hitl/action.php')) ?>">
      <input type="hidden" name="case_id" id="reviewCaseId">
      <input type="hidden" name="escalation_trigger" id="reviewEscalationTrigger">

      <label for="technicianSelect">Assign Technician</label>
      <select name="technician_id" id="technicianSelect" required>
        <option value="">Select technician</option>
      </select>

      <div class="review-modal-actions">
        <button type="button" class="review-secondary review-modal-close-button">Close</button>
        <button type="submit" class="review-approve">Approve &amp; Assign</button>
      </div>
    </form>
  </div>
</div>
