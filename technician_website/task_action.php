<?php
/**
 * SmartOps Source: technician_website/task_action.php
 * Purpose: Handle technician Accept, Start, Complete, and Support Request actions.
 *
 * Developer Guide: task lifecycle actions are separated so each business rule,
 * database update, and notification step can be explained independently.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/support_config.php';
require_once __DIR__ . '/../config/whatsapp.php';

// =============================================================================
// SECTION: Technician Authentication and Request Input
// =============================================================================
$techId = require_technician();
$caseId = trim((string) ($_POST['case_id'] ?? $_GET['case_id'] ?? ''));
$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));
$supportReason = trim((string) ($_POST['support_reason'] ?? ''));
$partName = trim((string) ($_POST['part_name'] ?? ''));
$otherPart = trim((string) ($_POST['other_part'] ?? ''));
$outsourcingService = trim((string) ($_POST['outsourcing_service'] ?? ''));
$partQuantity = max(1, (int) ($_POST['part_quantity'] ?? 1));
$partRemark = trim((string) ($_POST['part_remark'] ?? ''));
$seniorReason = trim((string) ($_POST['senior_reason'] ?? ''));
$seniorOtherReason = trim((string) ($_POST['senior_other_reason'] ?? ''));
$outsourcingOtherService = trim((string) ($_POST['outsourcing_other_service'] ?? ''));
$outsourcingReason = trim((string) ($_POST['outsourcing_reason'] ?? ''));
$outsourcingOtherReason = trim((string) ($_POST['outsourcing_other_reason'] ?? ''));

// =============================================================================
// SECTION: Assigned Task Query
// =============================================================================
$task = fetch_one(
    "SELECT
        tt.*,
        hc.case_id AS hitl_case_id,
        hc.hitl_reason,
        sm.ai_confidence
     FROM technician_tasks tt
     LEFT JOIN hitl_cases hc
        ON hc.case_id = tt.case_id
     LEFT JOIN smart_maintenance_tickets sm
        ON sm.case_id = tt.case_id
     WHERE tt.case_id = ?
       AND tt.technician_id = ?
     ORDER BY tt.id DESC
     LIMIT 1",
    [$caseId, $techId]
);

if (!$task) {
    redirect_to('home.php?filter=assigned');
}

// =============================================================================
// SECTION: Accept Task
// =============================================================================
if ($action === 'accept') {
    execute_sql(
        "UPDATE technician_tasks
         SET task_status = 'Accepted',
             accepted_time = NOW()
         WHERE id = ?",
        [$task['id']]
    );

    execute_sql(
        "UPDATE workforce_cases
         SET task_status = 'Accepted',
             work_stage = 'Assigned - Not Started',
             accepted_at = NOW()
         WHERE case_id = ?",
        [$caseId]
    );

    update_assignment_history_action($caseId, $techId, 'accept');
    refresh_live_sla_statuses();
    refresh_technician_summary($techId);

    // -------------------------------------------------------------------------
    // SECTION: Guest WhatsApp Notification on Acceptance
    // -------------------------------------------------------------------------
    // SmartOps calls the separate WhatsApp Bot bridge when the technician
    // accepts a case and a guest identity is available for that ticket.
    if (
        db_table_exists('smart_maintenance_tickets')
        && db_column_exists('smart_maintenance_tickets', 'guest_chat_id')
    ) {
        $guestTicket = fetch_one(
            "SELECT room_id, guest_phone, guest_chat_id, guest_message_id
             FROM smart_maintenance_tickets
             WHERE case_id = ?
             LIMIT 1",
            [$caseId]
        );

        $guestPhone = trim((string) ($guestTicket['guest_phone'] ?? ''));
        $guestChatId = trim((string) ($guestTicket['guest_chat_id'] ?? ''));
        $guestMessageId = trim((string) ($guestTicket['guest_message_id'] ?? ''));
        $hasGuestIdentity = $guestPhone !== '' || $guestChatId !== '' || $guestMessageId !== '';

        if ($hasGuestIdentity) {
            $technicianRecord = fetch_one(
                'SELECT name FROM technicians WHERE technician_id = ? LIMIT 1',
                [$techId]
            );
            $technicianName = trim((string) ($technicianRecord['name'] ?? $techId));

            $notifyResult = notify_guest_technician_accepted([
                'case_id' => $caseId,
                'room_id' => (string) ($guestTicket['room_id'] ?? $task['room'] ?? ''),
                'guest_phone' => $guestPhone,
                'guest_chat_id' => $guestChatId,
                'guest_message_id' => $guestMessageId,
                'technician_id' => $techId,
                'technician_name' => $technicianName,
            ]);

            // -----------------------------------------------------------------
            // SECTION: Store Guest Notification Result
            // -----------------------------------------------------------------
            if (db_column_exists('smart_maintenance_tickets', 'guest_notification_status')) {
                if (!empty($notifyResult['success'])) {
                    $method = (string) ($notifyResult['response']['sent_using'] ?? 'UNKNOWN');
                    $recipient = (string) ($notifyResult['response']['recipient_id'] ?? '');

                    if (db_column_exists('smart_maintenance_tickets', 'guest_notification_method')) {
                        execute_sql(
                            "UPDATE smart_maintenance_tickets
                             SET guest_start_notified_at = NOW(),
                                 guest_notification_status = 'SENT_ON_ACCEPT',
                                 guest_notification_error = NULL,
                                 guest_notification_method = ?,
                                 guest_notification_recipient = ?
                             WHERE case_id = ?",
                            [$method, $recipient, $caseId]
                        );
                    } else {
                        execute_sql(
                            "UPDATE smart_maintenance_tickets
                             SET guest_start_notified_at = NOW(),
                                 guest_notification_status = 'SENT_ON_ACCEPT',
                                 guest_notification_error = NULL
                             WHERE case_id = ?",
                            [$caseId]
                        );
                    }
                } else {
                    execute_sql(
                        "UPDATE smart_maintenance_tickets
                         SET guest_notification_status = 'FAILED_ON_ACCEPT',
                             guest_notification_error = ?
                         WHERE case_id = ?",
                        [
                            (string) ($notifyResult['error'] ?? 'Unknown WhatsApp notification error'),
                            $caseId,
                        ]
                    );
                }
            }
        } elseif (db_column_exists('smart_maintenance_tickets', 'guest_notification_status')) {
            execute_sql(
                "UPDATE smart_maintenance_tickets
                 SET guest_notification_status = 'SKIPPED_NO_GUEST',
                     guest_notification_error = 'No guest identity (message id, chat id or phone) stored for this case'
                 WHERE case_id = ?",
                [$caseId]
            );
        }
    }

    redirect_to('home.php?filter=assigned');
}

// =============================================================================
// SECTION: Start Repair
// =============================================================================
if ($action === 'start') {
    execute_sql(
        "UPDATE technician_tasks
         SET task_status = 'In Progress',
             started_time = NOW(),
             repair_sla_status = 'Pending'
         WHERE id = ?",
        [$task['id']]
    );

    execute_sql(
        "UPDATE workforce_cases
         SET work_stage = 'Repair In Progress',
             task_status = 'In Progress',
             started_at = NOW()
         WHERE case_id = ?",
        [$caseId]
    );

    update_assignment_history_action($caseId, $techId, 'start');
    refresh_live_sla_statuses();
    refresh_technician_summary($techId);

    redirect_to('home.php?filter=progress');
}

// =============================================================================
// SECTION: Complete Repair
// =============================================================================
if ($action === 'complete') {
    $completionNote = 'Repair completed by ' . $techId . '.';

    execute_sql(
        "UPDATE technician_tasks
         SET task_status = 'Completed',
             completed_time = NOW(),
             technician_note = ?
         WHERE id = ?",
        [$completionNote, $task['id']]
    );

    execute_sql(
        "UPDATE workforce_cases
         SET work_stage = 'Completed',
             task_status = 'Completed',
             completed_at = NOW(),
             technician_note = ?,
             assigned_technician_id = ?,
             technician_id = ?
         WHERE case_id = ?",
        [$completionNote, $techId, $techId, $caseId]
    );

    if (db_table_exists('smart_maintenance_tickets')) {
        execute_sql(
            "UPDATE smart_maintenance_tickets
             SET ticket_status = 'Completed',
                 completed_date = NOW(),
                 technician_assigned = ?,
                 assigned_to = ?
             WHERE case_id = ?",
            [$techId, $techId, $caseId]
        );
    }

    close_assignment_history($caseId, $techId, 'Repair Completed', $completionNote);
    refresh_live_sla_statuses();
    refresh_technician_summary($techId);

    // -------------------------------------------------------------------------
    // SECTION: Admin Completion Notification
    // -------------------------------------------------------------------------
    $technicianRecord = fetch_one(
        'SELECT name FROM technicians WHERE technician_id = ? LIMIT 1',
        [$techId]
    );
    $technicianName = trim((string) ($technicianRecord['name'] ?? $techId));
    $roomLabel = trim((string) ($task['room'] ?? ''));
    $issueLabel = trim((string) ($task['issue_summary'] ?? $task['issue'] ?? 'Maintenance task'));
    $notificationMessage = ($roomLabel !== '' ? 'Room ' . $roomLabel . ' · ' : '')
        . ($issueLabel !== '' ? $issueLabel . ' · ' : '')
        . 'Completed by ' . $technicianName . '.';

    create_admin_notification(
        'case-completed:' . $caseId,
        $caseId . ' has been completed',
        $notificationMessage,
        $caseId,
        $techId,
        'admin_portal/smart/case_detail.php?case_id=' . rawurlencode($caseId)
    );

    redirect_to('history.php');
}

// =============================================================================
// SECTION: Support Request Validation
// =============================================================================
if ($action === 'support') {
    $technician = current_technician();
    $assetKey = technician_support_asset_key((string) ($task['hotel_asset'] ?? ''));
    $allConfig = technician_support_config();
    $config = $allConfig[$assetKey] ?? $allConfig['special'];

    $allowedReasons = [];

    if (!empty($config['parts'])) {
        $allowedReasons[] = 'Parts Required';
    }
    if (technician_level_label($technician) === 'Junior') {
        $allowedReasons[] = 'Senior Support';
    }
    if (!empty($config['outsourcing_services'])) {
        $allowedReasons[] = 'Outsourcing';
    }

    if (!in_array($supportReason, $allowedReasons, true)) {
        redirect_to('home.php?filter=progress');
    }

    $requestedPart = '';
    $externalService = '';
    $outsourceReason = '';
    $supportDetail = '';

    // -------------------------------------------------------------------------
    // SECTION: Parts Required Request
    // -------------------------------------------------------------------------
    if ($supportReason === 'Parts Required') {
        if ($partName === 'Other') {
            if ($otherPart === '') {
                redirect_to('home.php?filter=progress');
            }
            $requestedPart = $otherPart;
        } elseif (in_array($partName, $config['parts'], true)) {
            $requestedPart = $partName;
        } else {
            redirect_to('home.php?filter=progress');
        }

        $supportDetail = 'Quantity: ' . $partQuantity
            . ($partRemark !== '' ? ' | Remark: ' . $partRemark : '');
        $supportNote = 'Parts Required - ' . $requestedPart
            . ' (Qty: ' . $partQuantity . ')'
            . ($partRemark !== '' ? ' - ' . $partRemark : '');
        $taskStatus = 'Waiting for Parts';
        $supportStatus = 'Waiting for Parts';

    // -------------------------------------------------------------------------
    // SECTION: Senior Support Request
    // -------------------------------------------------------------------------
    } elseif ($supportReason === 'Senior Support') {
        if ($seniorReason === 'Other') {
            if ($seniorOtherReason === '') {
                redirect_to('home.php?filter=progress');
            }
            $selectedReason = $seniorOtherReason;
        } elseif (in_array($seniorReason, $config['senior_reasons'], true)) {
            $selectedReason = $seniorReason;
        } else {
            redirect_to('home.php?filter=progress');
        }

        $supportDetail = $selectedReason;
        $supportNote = 'Senior Support - ' . $selectedReason;
        $taskStatus = 'Pending Senior Assignment';
        $supportStatus = 'Pending Senior Assignment';

    // -------------------------------------------------------------------------
    // SECTION: Outsourcing Request
    // -------------------------------------------------------------------------
    } else {
        if ($outsourcingService === 'Other') {
            if ($outsourcingOtherService === '') {
                redirect_to('home.php?filter=progress');
            }
            $externalService = $outsourcingOtherService;
        } elseif (in_array($outsourcingService, $config['outsourcing_services'], true)) {
            $externalService = $outsourcingService;
        } else {
            redirect_to('home.php?filter=progress');
        }

        if ($outsourcingReason === 'Other') {
            if ($outsourcingOtherReason === '') {
                redirect_to('home.php?filter=progress');
            }
            $outsourceReason = $outsourcingOtherReason;
        } elseif (in_array($outsourcingReason, $config['outsourcing_reasons'], true)) {
            $outsourceReason = $outsourcingReason;
        } else {
            redirect_to('home.php?filter=progress');
        }

        $supportNote = 'Outsourcing - ' . $externalService . ' - ' . $outsourceReason;
        $taskStatus = 'Pending Outsourcing';
        $supportStatus = 'Pending Outsourcing Decision';
    }

    // -------------------------------------------------------------------------
    // SECTION: Save Support Request Details
    // -------------------------------------------------------------------------
    execute_sql(
        "UPDATE technician_tasks
         SET requested_part = ?,
             required_external_service = ?,
             outsourcing_reason = ?,
             support_other_detail = ?
         WHERE id = ?",
        [
            $requestedPart,
            $externalService,
            $outsourceReason,
            $supportDetail,
            $task['id'],
        ]
    );

    execute_sql(
        "UPDATE workforce_cases
         SET requested_part = ?,
             required_external_service = ?,
             outsourcing_reason = ?,
             support_other_detail = ?,
             parts_status = CASE
                 WHEN ? = 'Parts Required' THEN 'Requested'
                 ELSE parts_status
             END,
             parts_requested_at = CASE
                 WHEN ? = 'Parts Required' THEN NOW()
                 ELSE parts_requested_at
             END,
             outsourcing_status = CASE
                 WHEN ? = 'Outsourcing' THEN 'Pending Decision'
                 ELSE outsourcing_status
             END,
             transfer_reason = CASE
                 WHEN ? = 'Senior Support' THEN 'Senior Support'
                 ELSE transfer_reason
             END,
             transfer_requested_at = CASE
                 WHEN ? = 'Senior Support' THEN NOW()
                 ELSE transfer_requested_at
             END
         WHERE case_id = ?",
        [
            $requestedPart,
            $externalService,
            $outsourceReason,
            $supportDetail,
            $supportReason,
            $supportReason,
            $supportReason,
            $supportReason,
            $supportReason,
            $caseId,
        ]
    );

    // -------------------------------------------------------------------------
    // SECTION: Release Task for Manager Support Workflow
    // -------------------------------------------------------------------------
    release_case_from_technician(
        $caseId,
        $techId,
        $taskStatus,
        $supportReason,
        $supportStatus,
        $supportNote
    );

    redirect_to('home.php?filter=support');
}

// =============================================================================
// SECTION: Default Redirect
// =============================================================================
redirect_to('home.php?filter=assigned');
