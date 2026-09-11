<?php
/**
 * SmartOps Source: admin_portal/notifications/open.php
 * Purpose: Admin Portal / Notifications: Open server-side page, endpoint, or reusable module.
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

$id = max(0, (int)($_GET['id'] ?? 0));
$notification = find_admin_notification($id);
if (!$notification) {
    redirect_to(app_url('admin_portal/smart/dashboard.php'));
}

mark_admin_notification_read($id);
$target = ltrim(trim((string)($notification['target_path'] ?? '')), '/');
if ($target === '' || str_contains($target, '://') || str_starts_with($target, '//')) {
    $target = 'admin_portal/smart/dashboard.php';
}
redirect_to(app_url($target));
