<?php
/**
 * SmartOps Source: admin_portal/api/notifications.php
 * Purpose: Admin Portal / API: Notifications server-side page, endpoint, or reusable module.
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
if (empty($_SESSION['admin_email'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

// =============================================================================
// SECTION: JSON Response Headers
// =============================================================================
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// =============================================================================
// SECTION: Notification Records
// =============================================================================
$rows = fetch_admin_notifications(8);
$notifications = [];
foreach ($rows as $row) {
    $id = (int)($row['id'] ?? 0);
    $notifications[] = [
        'id' => $id,
        'title' => (string)($row['title'] ?? 'Case completed'),
        'message' => (string)($row['message'] ?? ''),
        'case_id' => (string)($row['case_id'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'unread' => empty($row['read_at']),
        'open_url' => app_url('admin_portal/notifications/open.php?id=' . $id),
    ];
}

// =============================================================================
// SECTION: Notification JSON Response
// =============================================================================
echo json_encode([
    'success' => true,
    'unread_count' => admin_unread_notification_count(),
    'notifications' => $notifications,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
