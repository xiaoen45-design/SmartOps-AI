<?php
/**
 * SmartOps Source: admin_portal/hitl/daily_summary_helpers.php
 * Purpose: Admin Portal / HITL: Daily Summary Helpers server-side page, endpoint, or reusable module.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

declare(strict_types=1);


// =============================================================================
// SECTION: HITL Daily Valid Date
// =============================================================================
function hitl_daily_valid_date(string $value): string {
    $value = trim($value);
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : date('Y-m-d');
}


// =============================================================================
// SECTION: HITL Daily Valid Queue
// =============================================================================
function hitl_daily_valid_queue(string $value): string {
    $allowed = ['All', 'Critical', 'High', 'Low Confidence', 'Out of Knowledge Base'];
    return in_array($value, $allowed, true) ? $value : 'All';
}


// =============================================================================
// SECTION: HITL Daily Queue Label
// =============================================================================
function hitl_daily_queue_label(string $queue): string {
    return match ($queue) {
        'Critical' => 'Immediate',
        'High' => 'Urgent',
        'Out of Knowledge Base' => 'Out of Knowledge Base',
        'Low Confidence' => 'Low Confidence',
        default => 'All HITL Queues',
    };
}


// =============================================================================
// SECTION: HITL Daily Fetch Rows
// =============================================================================
function hitl_daily_fetch_rows(
    string $date,
    string $queue = 'All',
    string $asset = '',
    string $component = ''
): array {
    $hasHitlCreatedAt = db_column_exists('hitl_cases', 'created_at');

    // =============================================================================
    // SECTION: Database Queries and Data Preparation
    // =============================================================================
    $rows = fetch_all(
        "SELECT h.*, s.ai_confidence, s.created_at AS ticket_created_at
         FROM hitl_cases h
         LEFT JOIN smart_maintenance_tickets s ON s.case_id = h.case_id
         ORDER BY h.id DESC"
    );

    $cleanAsset = clean_asset_name($asset);

    return array_values(array_filter($rows, static function (array $row) use (
        $date,
        $queue,
        $asset,
        $cleanAsset,
        $component,
        $hasHitlCreatedAt
    ): bool {
        $hitlTimestamp = $hasHitlCreatedAt ? strtotime((string)($row['created_at'] ?? '')) : false;
        $ticketTimestamp = strtotime((string)($row['ticket_created_at'] ?? ''));
        $eventTimestamp = $hitlTimestamp ?: $ticketTimestamp;
        if (!$eventTimestamp || date('Y-m-d', $eventTimestamp) !== $date) return false;

        $trigger = hitl_primary_escalation_trigger($row);
        if ($queue !== 'All' && $trigger !== $queue) return false;

        if ($asset !== '') {
            $rowAsset = asset_display_name($row['hotel_asset'] ?? 'Other');
            if ($rowAsset !== asset_display_name($asset)
                && clean_asset_name((string)($row['hotel_asset'] ?? '')) !== $cleanAsset) {
                return false;
            }
        }

        if ($component !== '' && (string)($row['component'] ?? 'Unknown') !== $component) {
            return false;
        }

        $row['_daily_trigger'] = $trigger;
        return true;
    }));
}


// =============================================================================
// SECTION: HITL Daily Breakdown
// =============================================================================
function hitl_daily_breakdown(array $rows): array {
    $result = [
        'total' => count($rows),
        'immediate' => 0,
        'urgent' => 0,
        'low_confidence' => 0,
        'out_of_kb' => 0,
    ];

    foreach ($rows as $row) {
        $trigger = hitl_primary_escalation_trigger($row);
        if ($trigger === 'Critical') $result['immediate']++;
        elseif ($trigger === 'High') $result['urgent']++;
        elseif ($trigger === 'Low Confidence') $result['low_confidence']++;
        elseif ($trigger === 'Out of Knowledge Base') $result['out_of_kb']++;
    }

    return $result;
}


// =============================================================================
// SECTION: HITL Seven Day Window Bounds
// =============================================================================
function hitl_seven_day_window_bounds(): array {
    // "Latest 7 days" is always anchored to today's Kuala Lumpur date,
    // not to the newest case timestamp in the database.
    $referenceDay = strtotime(date('Y-m-d 00:00:00'));
    $startTimestamp = strtotime('-6 days', $referenceDay);
    $endTimestamp = strtotime('+1 day', $referenceDay);
    return [$startTimestamp, $endTimestamp, $referenceDay];
}


// =============================================================================
// SECTION: HITL Seven Day Fetch Rows
// =============================================================================
function hitl_seven_day_fetch_rows(
    string $queue = 'All',
    string $asset = '',
    string $component = ''
): array {
    $hasHitlCreatedAt = db_column_exists('hitl_cases', 'created_at');
    $rows = fetch_all(
        "SELECT h.*, s.ai_confidence, s.created_at AS ticket_created_at
         FROM hitl_cases h
         LEFT JOIN smart_maintenance_tickets s ON s.case_id = h.case_id
         ORDER BY h.id DESC"
    );

    foreach ($rows as &$row) {
        $hitlTimestamp = $hasHitlCreatedAt ? strtotime((string)($row['created_at'] ?? '')) : false;
        $ticketTimestamp = strtotime((string)($row['ticket_created_at'] ?? ''));
        $row['_seven_day_ts'] = $hitlTimestamp ?: $ticketTimestamp ?: 0;
    }
    unset($row);

    [$startTimestamp, $endTimestamp] = hitl_seven_day_window_bounds();
    $cleanAsset = clean_asset_name($asset);

    return array_values(array_filter($rows, static function (array $row) use (
        $queue,
        $asset,
        $cleanAsset,
        $component,
        $startTimestamp,
        $endTimestamp
    ): bool {
        $eventTimestamp = (int)($row['_seven_day_ts'] ?? 0);
        if ($eventTimestamp < $startTimestamp || $eventTimestamp >= $endTimestamp) return false;

        $trigger = hitl_primary_escalation_trigger($row);
        if ($queue !== 'All' && $trigger !== $queue) return false;

        if ($asset !== '') {
            $rowAsset = asset_display_name($row['hotel_asset'] ?? 'Other');
            if ($rowAsset !== asset_display_name($asset)
                && clean_asset_name((string)($row['hotel_asset'] ?? '')) !== $cleanAsset) {
                return false;
            }
        }

        if ($component !== '' && (string)($row['component'] ?? 'Unknown') !== $component) {
            return false;
        }

        return true;
    }));
}


// =============================================================================
// SECTION: HITL Seven Day Window Label
// =============================================================================
function hitl_seven_day_window_label(): string {
    [$startDay, , $referenceDay] = hitl_seven_day_window_bounds();
    return date('d M Y', $startDay) . ' – ' . date('d M Y', $referenceDay);
}
