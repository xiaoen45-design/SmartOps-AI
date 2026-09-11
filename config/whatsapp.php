<?php
/**
 * SmartOps Source: config/whatsapp.php
 * Purpose: WhatsApp integration configuration values.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */
/**
 * SmartOps -> separate WhatsApp_Bot project bridge.
 * Keep WhatsApp_Bot running separately with `npm start`.
 */


// =============================================================================
// SECTION: SmartOps Whatsapp Bridge URL
// =============================================================================
function smartops_whatsapp_bridge_url(): string {
    $configured = getenv('WHATSAPP_BRIDGE_URL');
    return rtrim($configured !== false && trim($configured) !== '' ? trim($configured) : 'http://127.0.0.1:8002', '/');
}


// =============================================================================
// SECTION: Notify Guest Technician Accepted
// =============================================================================
function notify_guest_technician_accepted(array $payload): array {
    $guestPhone = trim((string)($payload['guest_phone'] ?? ''));
    $guestChatId = trim((string)($payload['guest_chat_id'] ?? ''));
    $guestMessageId = trim((string)($payload['guest_message_id'] ?? ''));
    if ($guestPhone === '' && $guestChatId === '' && $guestMessageId === '') {
        return ['success' => false, 'skipped' => true, 'error' => 'Missing all guest identity fields'];
    }

    $url = smartops_whatsapp_bridge_url() . '/api/notify-technician-accepted';
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
    ]);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $curlError !== '') {
        return ['success' => false, 'error' => $curlError ?: 'WhatsApp bridge request failed'];
    }

    $decoded = json_decode((string)$body, true);
    if ($status < 200 || $status >= 300 || !is_array($decoded) || empty($decoded['success'])) {
        return [
            'success' => false,
            'error' => is_array($decoded) ? (string)($decoded['error'] ?? 'WhatsApp bridge returned an error') : 'Invalid WhatsApp bridge response',
            'http_status' => $status,
        ];
    }

    return ['success' => true, 'response' => $decoded];
}


// Backward-compatible alias for any older code paths.

// =============================================================================
// SECTION: Notify Guest Technician Started
// =============================================================================
function notify_guest_technician_started(array $payload): array {
    return notify_guest_technician_accepted($payload);
}
