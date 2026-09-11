<?php
/**
 * SmartOps Source: admin_portal/admin_auth.php
 * Purpose: Admin session access control and identity helpers.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/../includes/helpers.php';


// =============================================================================
// SECTION: Current Admin Email
// =============================================================================
function current_admin_email(): string {
    return (string)($_SESSION['admin_email'] ?? 'admin@smartops.com');
}


// =============================================================================
// SECTION: Current Admin Name
// =============================================================================
function current_admin_name(): string {
    return (string)($_SESSION['admin_name'] ?? 'SmartOps Admin');
}


// =============================================================================
// SECTION: Authentication and Access Control
// =============================================================================
function require_admin(): void {
    if (empty($_SESSION['admin_email'])) {
        redirect_to(app_url('admin_portal/login.php'));
    }
    smartops_sync_pending_ai_cases();
}


// =============================================================================
// SECTION: Admin Access Code
// =============================================================================
function admin_access_code(): string {
    $configured = getenv('SMARTOPS_ADMIN_ACCESS_CODE');
    if ($configured !== false && trim($configured) !== '') {
        return trim($configured);
    }

    global $SMARTOPS_LOCAL_CONFIG;
    return trim((string)($SMARTOPS_LOCAL_CONFIG['admin_access_code'] ?? ''));
}
