<?php
/**
 * SmartOps Source: config/api.php
 * Purpose: FastAPI and external API endpoint configuration.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */
// =============================================================================
// SECTION: SmartOps API Base URL
// =============================================================================
// SmartOps workflow API used by the PHP websites. The separate ML Team API remains configured independently.
define('SMARTOPS_API_BASE_URL', rtrim(getenv('SMARTOPS_API_BASE_URL') ?: 'http://127.0.0.1:8001', '/'));

/** Send JSON to FastAPI without breaking the website when the API is offline. */

// =============================================================================
// SECTION: SmartOps API POST Request
// =============================================================================
function smartops_api_post(string $endpoint, ?array $payload = null, int $timeoutSeconds = 5): array {
    $url = SMARTOPS_API_BASE_URL . '/' . ltrim($endpoint, '/');
    $body = json_encode($payload ?? new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => $body,
        ]);
        $response = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false || $error !== '' || $status < 200 || $status >= 300) {
            return ['success' => false, 'status' => $status, 'error' => $error !== '' ? $error : 'FastAPI returned HTTP ' . $status];
        }
        $decoded = json_decode((string)$response, true);
        return ['success' => true, 'status' => $status, 'data' => is_array($decoded) ? $decoded : ['raw' => $response]];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $body,
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return ['success' => false, 'status' => 0, 'error' => 'FastAPI is unavailable'];
    }
    $status = 200;
    if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
        $status = (int)$match[1];
    }
    if ($status < 200 || $status >= 300) {
        return ['success' => false, 'status' => $status, 'error' => 'FastAPI returned HTTP ' . $status];
    }
    $decoded = json_decode((string)$response, true);
    return ['success' => true, 'status' => $status, 'data' => is_array($decoded) ? $decoded : ['raw' => $response]];
}
