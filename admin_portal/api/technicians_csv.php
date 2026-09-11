<?php
/**
 * SmartOps Source: admin_portal/api/technicians_csv.php
 * Purpose: Admin Portal / API: Technicians CSV server-side page, endpoint, or reusable module.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */
// SQL-backed endpoint: data is queried live from the shared smartops database.

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/csv_common.php';
$headers = ['technician_id','name','status','department','role','technician_level','total_tasks','active_tasks','completed_tasks','overdue_tasks','workload_percent'];
try {
    if (!db_table_exists('technicians')) empty_csv('technicians.csv', $headers);

    // =============================================================================
    // SECTION: Database Queries and Data Preparation
    // =============================================================================
    $rows = fetch_all("SELECT technician_id,name,status,department,role,technician_level,total_tasks,active_tasks,completed_tasks,overdue_tasks,workload_percent FROM technicians WHERE is_active=1 ORDER BY technician_id");
    emit_csv('technicians.csv', $headers, $rows);
} catch (Throwable $e) { empty_csv('technicians.csv', $headers); }
