<?php
/**
 * SmartOps Source: admin_portal/logout.php
 * Purpose: Admin Portal: Logout server-side page, endpoint, or reusable module.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/admin_auth.php';

// =============================================================================
// SECTION: Admin Session Cleanup
// =============================================================================
unset($_SESSION['admin_email'], $_SESSION['admin_name']);

// =============================================================================
// SECTION: Redirect to Login
// =============================================================================
redirect_to('login.php');
