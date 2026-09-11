<?php
/**
 * SmartOps Source: admin_portal/api/csv_common.php
 * Purpose: Admin Portal / API: CSV Common server-side page, endpoint, or reusable module.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/../admin_auth.php';

// =============================================================================
// SECTION: Authentication and Access Control
// =============================================================================
require_admin();


// =============================================================================
// SECTION: CSV Clean
// =============================================================================
function csv_clean($value): string {
    return (string)($value ?? '');
}


// =============================================================================
// SECTION: Emit CSV
// =============================================================================
function emit_csv(string $filename, array $headers, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: inline; filename="'.$filename.'"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $h) $line[] = csv_clean($row[$h] ?? '');
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}


// =============================================================================
// SECTION: Empty CSV
// =============================================================================
function empty_csv(string $filename, array $headers): void {
    emit_csv($filename, $headers, []);
}
