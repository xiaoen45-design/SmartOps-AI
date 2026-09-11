<?php
/**
 * SmartOps Source: admin_portal/api/hitl_csv.php
 * Purpose: Admin Portal / API: HITL CSV server-side page, endpoint, or reusable module.
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
$headers = ['case_id','room','issue_summary','issue','hotel_asset','component','severity','priority','hitl_reason','safety_flag','review_status','manager_comment'];
try {
    if (!db_table_exists('hitl_cases')) empty_csv('hitl_cases.csv', $headers);

    // =============================================================================
    // SECTION: Database Queries and Data Preparation
    // =============================================================================
    $rows = fetch_all("SELECT case_id,room,issue_summary,issue,hotel_asset,component,severity,priority,hitl_reason,safety_flag,review_status,manager_comment FROM hitl_cases ORDER BY case_id");
    foreach ($rows as &$row) {
        $rawAsset = $row['hotel_asset'] ?? '';
        $row['hotel_asset'] = asset_display_name($rawAsset);
        $row['component'] = component_display_name($row['component'] ?? '', $rawAsset);
        $row['priority'] = $row['priority'] ? priority_display_name($row['priority']) : '';
    }
    unset($row);
    emit_csv('hitl_cases.csv', $headers, $rows);
} catch (Throwable $e) { empty_csv('hitl_cases.csv', $headers); }
