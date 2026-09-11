<?php
/**
 * SmartOps Source: admin_portal/api/workforce_csv.php
 * Purpose: Admin Portal / API: Workforce CSV server-side page, endpoint, or reusable module.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */
// SQL-backed endpoint: data is queried live from the shared smartops database.

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/csv_common.php';
ensure_hitl_dataset_consistency();
// =============================================================================
// SECTION: Workforce CSV Columns
// =============================================================================
$headers = [
    'case_id',
    'room',
    'issue_summary',
    'issue',
    'hotel_asset',
    'component',
    'severity',
    'priority',
    'work_stage',
    'task_status',
    'sla_status',
    'support_reason',
    'support_status',
    'required_external_service',
    'outsourcing_reason',
    'requested_part',
    'support_other_detail',
    'assigned_technician_id',
    'previous_technician_id',
    'support_requested_at',
    'released_at',
    'parts_status',
    'parts_requested_at',
    'parts_ordered_at',
    'parts_ready_at',
    'outsourcing_status',
    'outsourced_at',
    'assignment_round',
    'assigned_at',
    'accepted_at',
    'started_at',
    'completed_at',
    'response_minutes',
    'repair_minutes',
];

// =============================================================================
// SECTION: Live SLA Refresh
// =============================================================================
refresh_live_sla_statuses();
try {
    if (!db_table_exists('workforce_cases')) empty_csv('workforce_cases.csv', $headers);

    // =============================================================================
    // SECTION: Database Queries and Data Preparation
    // =============================================================================
    $sql = <<<'SQL'
SELECT
    case_id,
    room,
    issue_summary,
    issue,
    hotel_asset,
    component,
    severity,
    priority,
    work_stage,
    task_status,
    sla_status,
    support_reason,
    support_status,
    required_external_service,
    outsourcing_reason,
    requested_part,
    support_other_detail,
    assigned_technician_id,
    previous_technician_id,
    support_requested_at,
    released_at,
    parts_status,
    parts_requested_at,
    parts_ordered_at,
    parts_ready_at,
    outsourcing_status,
    outsourced_at,
    assignment_round,
    assigned_at,
    accepted_at,
    started_at,
    completed_at,
    response_minutes,
    repair_minutes
FROM workforce_cases
ORDER BY case_id
SQL;
    $rows = fetch_all($sql);
    // =============================================================================
    // SECTION: Workforce Display Label Normalization
    // =============================================================================
    foreach ($rows as &$row) {
        $rawAsset = $row['hotel_asset'] ?? '';
        $row['hotel_asset'] = asset_display_name($rawAsset);
        $row['component'] = component_display_name($row['component'] ?? '', $rawAsset);
        $row['priority'] = $row['priority'] ? priority_display_name($row['priority']) : '';
    }
    unset($row);
    // =============================================================================
    // SECTION: Workforce CSV Response
    // =============================================================================
    emit_csv('workforce_cases.csv', $headers, $rows);
} catch (Throwable $e) {
    empty_csv('workforce_cases.csv', $headers);
}
