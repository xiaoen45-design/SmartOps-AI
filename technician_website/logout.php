<?php
/**
 * SmartOps Source: technician_website/logout.php
 * Purpose: End the Technician session and return the user to the login page.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/../includes/helpers.php';

// =============================================================================
// SECTION: Session Logout
// =============================================================================
session_destroy();
redirect_to('login.php');
