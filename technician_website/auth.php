<?php
/**
 * SmartOps Source: technician_website/auth.php
 * Purpose: Technician Website: Auth server-side page, endpoint, or reusable module.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/../includes/technician_layout.php';


// =============================================================================
// SECTION: Authentication and Access Control
// =============================================================================
function require_technician(): string {
    $technicianId = trim((string)($_SESSION['technician_id'] ?? ''));
    if ($technicianId === '') redirect_to('login.php');

    $technician = find_technician($technicianId);
    if (!$technician) {
        unset($_SESSION['technician_id']);
        redirect_to('login.php?access=removed');
    }
    smartops_sync_pending_ai_cases();
    return $technicianId;
}


// =============================================================================
// SECTION: Current Technician
// =============================================================================
function current_technician(): ?array {
    return !empty($_SESSION['technician_id']) ? find_technician((string)$_SESSION['technician_id']) : null;
}
