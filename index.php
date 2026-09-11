<?php
/**
 * SmartOps Source: index.php
 * Purpose: Index server-side entry point.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/includes/helpers.php';

// =============================================================================
// SECTION: Redirect to Admin Login
// =============================================================================
redirect_to(app_url('admin_portal/login.php'));
