<?php
/**
 * SmartOps Source: admin_portal/index.php
 * Purpose: Admin Portal: Index server-side page, endpoint, or reusable module.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/admin_auth.php';

// =============================================================================
// SECTION: Authentication and Access Control
// =============================================================================
require_admin();

$page = $_GET['page'] ?? 'overview';
if ($page === 'hitl') {
    redirect_to(app_url('admin_portal/hitl/dashboard.php'));
}
if ($page === 'workforce') {
    redirect_to(app_url('admin_portal/workforce/dashboard.php'));
}
redirect_to(app_url('admin_portal/overview/dashboard.php'));
