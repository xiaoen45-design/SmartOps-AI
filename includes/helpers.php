<?php
/**
 * SmartOps Source: includes/helpers.php
 * Purpose: Shared database helpers, business rules, SLA logic, case synchronization, and reusable utility functions.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/api.php';

const SMARTOPS_MAX_ACTIVE_TASKS = 1;


// =============================================================================
// SECTION: HTML Escape Helper
// =============================================================================
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }


/**
 * Ask FastAPI to assign only existing, unassigned AI Automation cases.
 * It does not re-import HITL records or reset manager review decisions.
 */

// =============================================================================
// SECTION: SmartOps Sync Pending AI Cases
// =============================================================================
function smartops_sync_pending_ai_cases(bool $force = false): array {
    static $ranThisRequest = false;
    if ($ranThisRequest && !$force) {
        return ['success' => true, 'skipped' => true, 'reason' => 'already_checked'];
    }
    $ranThisRequest = true;

    try {
        if (!db_table_exists('workforce_cases') || !db_table_exists('hitl_cases')) {
            return ['success' => false, 'skipped' => true, 'reason' => 'database_not_ready'];
        }

        // =============================================================================
        // SECTION: Database Queries and Data Preparation
        // =============================================================================
        $pending = scalar(
            "SELECT COUNT(*)
             FROM workforce_cases w
             INNER JOIN cases c ON c.case_id=w.case_id
             WHERE COALESCE(w.work_stage,'')='Pending Assignment'
               AND COALESCE(w.assigned_technician_id,'')=''
               AND c.source_module='AI Automation'"
        );
        if ($pending <= 0) {
            return ['success' => true, 'skipped' => true, 'reason' => 'no_pending_ai_cases'];
        }
    } catch (Throwable $exception) {
        return ['success' => false, 'skipped' => true, 'reason' => 'database_not_ready'];
    }

    $lastAttempt = (int)($_SESSION['smartops_fastapi_sync_at'] ?? 0);
    if (!$force && $lastAttempt > 0 && (time() - $lastAttempt) < 5) {
        return ['success' => true, 'skipped' => true, 'reason' => 'throttled'];
    }
    $_SESSION['smartops_fastapi_sync_at'] = time();

    $result = smartops_api_post('/api/v1/auto-assign-pending');
    $_SESSION['smartops_fastapi_last_sync_ok'] = !empty($result['success']);
    $_SESSION['smartops_fastapi_last_sync_result'] = $result['success']
        ? 'FastAPI assignment completed'
        : (string)($result['error'] ?? 'FastAPI assignment failed');
    return $result;
}


// =============================================================================
// SECTION: Redirect To
// =============================================================================
function redirect_to(string $url): void { header("Location: {$url}"); exit; }


// =============================================================================
// SECTION: App Base URL
// =============================================================================
function app_base_url(): string {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $markers = ['/admin_portal/', '/technician_website/', '/includes/', '/config/'];
    foreach ($markers as $marker) {
        $pos = strpos($script, $marker);
        if ($pos !== false) {
            return rtrim(substr($script, 0, $pos), '/');
        }
    }
    $dir = rtrim(dirname($script), '/');
    return $dir === '/' ? '' : $dir;
}


// =============================================================================
// SECTION: App URL
// =============================================================================
function app_url(string $path = ''): string {
    $base = app_base_url();
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}


// =============================================================================
// SECTION: Asset File Version
// =============================================================================
function asset_file_version(string $relativePath): string {
    $path = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
    if (is_file($path)) {
        $hash = @md5_file($path);
        if (is_string($hash) && $hash !== '') return substr($hash, 0, 12);
    }
    return 'smartops';
}


// =============================================================================
// SECTION: Fetch All
// =============================================================================
function fetch_all(string $sql, array $params = []): array {
    $stmt = smartops_pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}


// =============================================================================
// SECTION: Fetch One
// =============================================================================
function fetch_one(string $sql, array $params = []): ?array {
    $stmt = smartops_pdo()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}


// =============================================================================
// SECTION: Scalar
// =============================================================================
function scalar(string $sql, array $params = []): int {
    $stmt = smartops_pdo()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}


// =============================================================================
// SECTION: Execute SQL
// =============================================================================
function execute_sql(string $sql, array $params = []): void {
    $stmt = smartops_pdo()->prepare($sql);
    $stmt->execute($params);
}

/**
 * Canonical Hotel Asset label used by MySQL, dashboards and Technician Website.
 * Canonical asset names are used internally for matching; D-coded component labels are preserved for display.
 */

// =============================================================================
// SECTION: Clean Asset Name
// =============================================================================
function clean_asset_name(?string $asset): string {
    $value = trim((string)$asset);
    $normalizedRaw = strtolower($value);

    if ($value === '' || in_array($normalizedRaw, ['-', 'unknown', 'none', 'null', 'n/a', 'na', 'out_of_kb', 'out of kb', 'out of knowledge base'], true)) {
        return 'Other';
    }

    // Strip a leading D-category code only for internal matching.
    // User-facing output is produced by asset_display_name(), which restores it.
    $value = preg_replace('/^D\d+(?:\.\d+)?\s*/i', '', $value) ?? $value;
    $value = trim((string)$value);
    $normalized = strtolower($value);

    return match ($normalized) {
        'hvac' => 'HVAC',
        'plumbing' => 'Plumbing',
        'electrical' => 'Electrical',
        'fire', 'fire protection', 'fire system' => 'Fire Protection',
        'elevator', 'conveying', 'elevator & lifts', 'lifts' => 'Conveying',
        'other' => 'Other',
        default => $value !== '' ? $value : 'Other',
    };
}


// =============================================================================
// SECTION: Asset Display Name
// =============================================================================
function asset_display_name(?string $asset): string {
    $clean = clean_asset_name($asset);
    return match (strtolower($clean)) {
        'conveying' => 'D10 Conveying',
        'plumbing' => 'D20 Plumbing',
        'hvac' => 'D30 HVAC',
        'fire protection' => 'D40 Fire Protection',
        'electrical' => 'D50 Electrical',
        'other' => 'Other',
        default => $clean,
    };
}


// =============================================================================
// SECTION: Normalize Technician Department
// =============================================================================
function normalize_technician_department(?string $department): string {
    $value = trim((string)$department);
    if ($value === '') return '';

    $value = preg_replace('/^D\d+\s+/', '', $value);
    $normalized = strtolower(trim($value));

    return match ($normalized) {
        'hvac' => 'HVAC',
        'plumbing' => 'Plumbing',
        'electrical' => 'Electrical',
        'fire', 'fire protection', 'fire system' => 'Fire Protection',
        'elevator', 'conveying', 'elevator & lifts', 'lifts' => 'Conveying',
        'hitl', 'technical specialist', 'out of knowledge base', 'all department', 'all departments' => 'All Departments',
        default => $value,
    };
}


// =============================================================================
// SECTION: Technician Department Label
// =============================================================================
function technician_department_label(?array $tech): string {
    if (strtolower(trim((string)($tech['role'] ?? ''))) === 'technical specialist') {
        return 'All Departments';
    }
    $department = trim((string)($tech['department'] ?? ''));
    if ($department === '') $department = trim((string)($tech['category'] ?? ''));
    $label = normalize_technician_department($department);
    return $label !== '' ? $label : '-';
}

/**
 * User-facing workflow role.
 * The database keeps the short canonical roles used by assignment rules:
 * Technician = normal AI-routed work; Technical Specialist = HITL work.
 */

// =============================================================================
// SECTION: Technician Workflow Role Label
// =============================================================================
function technician_workflow_role_label(?array $tech): string {
    $role = strtolower(trim((string)($tech['role'] ?? '')));
    return match ($role) {
        'technical specialist', 'hitl technician' => 'HITL Technician',
        'technician', 'ai automation technician', 'senior technician', 'senior support technician' => 'AI Automation Technician',
        default => trim((string)($tech['role'] ?? '')) ?: '-',
    };
}


// =============================================================================
// SECTION: Clean Component Name
// =============================================================================
function clean_component_name(?string $component): string {
    $component = trim((string)$component);
    return $component !== '' ? $component : 'Maintenance';
}


// =============================================================================
// SECTION: Component Code Value
// =============================================================================
function component_code_value(?string $asset, ?string $component): string {
    $assetKey = strtolower(clean_asset_name($asset));
    $clean = clean_component_name($component);
    $componentKey = strtolower($clean);

    $map = [
        'hvac' => [
            'energy supply' => 'D3010',
            'heat generating systems' => 'D3020',
            'cooling generating systems' => 'D3030',
            'distribution systems' => 'D3040',
            'terminal & package units' => 'D3050',
            'controls & instrumentation' => 'D3060',
            'systems testing & balancing' => 'D3070',
        ],
        'plumbing' => [
            'plumbing fixtures' => 'D2010',
            'domestic water distribution' => 'D2020',
            'sanitary waste' => 'D2030',
        ],
        'electrical' => [
            'electrical service & distribution' => 'D5010',
            'lighting & branch wiring' => 'D5020',
        ],
        'fire protection' => [
            'sprinklers' => 'D4010',
            'other fire protection system' => 'D4090',
        ],
        'conveying' => [
            'elevator & lifts' => 'D1010',
        ],
    ];

    if ($assetKey !== '' && isset($map[$assetKey][$componentKey])) {
        return $map[$assetKey][$componentKey];
    }
    foreach ($map as $componentMap) {
        if (isset($componentMap[$componentKey])) return $componentMap[$componentKey];
    }
    return '';
}


// =============================================================================
// SECTION: Component Display Name
// =============================================================================
function component_display_name(?string $component, ?string $asset = null): string {
    $component = trim((string)$component);
    if ($component === '') return '-';
    if (preg_match('/^D\d{4}\s+/', $component)) return $component;

    $clean = clean_component_name($component);
    $code = component_code_value($asset, $clean);
    return $code !== '' ? $code . ' ' . $clean : $clean;
}


/**
 * Canonical D-code for SmartOps failure modes found in the project KB/dataset.
 * If a failure mode has no dedicated code, fall back to the component D-code
 * so every user-facing failure-mode label still carries its maintenance code.
 */

// =============================================================================
// SECTION: Failure Mode Code Value
// =============================================================================
function failure_mode_code_value(?string $failureMode, ?string $component = null, ?string $asset = null): string {
    $failure = trim((string)$failureMode);
    if ($failure === '') return '';
    if (preg_match('/^(D\d{4})\s+/i', $failure, $m)) return strtoupper($m[1]);

    $key = strtolower($failure);
    $map = [
        'cold water service' => 'D2021',
        'hot water service' => 'D2022',
        'water supply system' => 'D2023',
        'main electrical supply' => 'D5010',
        'elevators related' => 'D1010',
        'exhaust ventilation systems' => 'D3040',
        'hot water distribution' => 'D3040',
        'air distribution systems' => 'D3040',
        'fire extinguishers' => 'D4030',
        'exit light' => 'D4090',
        'water leakage related' => 'D4010',
        'fire alarm system & smoke detector related' => 'D4090',
        'faulty faucets' => 'D2010',
        'lighting not working related' => 'D5020',
        'clogging and blockage' => 'D4010',
        'sensitivity failure' => 'D4010',
        'disposal' => 'D2030',
        'drains' => 'D2010',
        'floor drain' => 'D2030',
        'toilet' => 'D2010',
        'terminal self-contained units / fcu' => 'D3050',
    ];
    if (isset($map[$key])) return $map[$key];

    $componentDisplay = component_display_name($component, $asset);
    if (preg_match('/^(D\d{4})\s+/i', $componentDisplay, $m)) return strtoupper($m[1]);
    return '';
}


// =============================================================================
// SECTION: Failure Mode Display Name
// =============================================================================
function failure_mode_display_name(?string $failureMode, ?string $component = null, ?string $asset = null): string {
    $failure = trim((string)$failureMode);
    if ($failure === '') return '-';
    if (preg_match('/^D\d{4}\s+/i', $failure)) return $failure;
    $code = failure_mode_code_value($failure, $component, $asset);
    return $code !== '' ? $code . ' ' . $failure : $failure;
}


// =============================================================================
// SECTION: Technician Issue Title
// =============================================================================
function technician_issue_title(array $task): string {
    $summary = trim((string)($task['issue_summary'] ?? ''));
    $issue = trim((string)($task['issue'] ?? ''));
    $asset = trim((string)($task['hotel_asset'] ?? ''));
    $component = trim($task['component'] ?? '');

    $base = $summary !== '' ? $summary : $issue;
    $lower = strtolower($base);

    if ($base === '' || str_contains($lower, 'system requires inspection') || str_contains($lower, 'requires inspection')) {
        $displayAsset = asset_display_name($asset);
        $displayComponent = component_display_name($component, $asset);
        if ($asset !== '' && $component !== '') return $displayAsset . ' - ' . $displayComponent . ' inspection';
        if ($component !== '') return $displayComponent . ' inspection';
    }

    return $base;
}


/**
 * Admin completion notifications
 * ------------------------------
 * Created lazily so existing localhost databases do not need to be re-imported.
 */

// =============================================================================
// SECTION: Ensure Admin Notifications Table
// =============================================================================
function ensure_admin_notifications_table(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;

    try {
        smartops_pdo()->exec(
            "CREATE TABLE IF NOT EXISTS admin_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_key VARCHAR(120) NOT NULL,
                notification_type VARCHAR(60) NOT NULL DEFAULT 'case_completed',
                title VARCHAR(255) NOT NULL,
                message TEXT NULL,
                case_id VARCHAR(20) NULL,
                technician_id VARCHAR(20) NULL,
                target_path VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                read_at DATETIME NULL,
                UNIQUE KEY uk_admin_notification_event (event_key),
                INDEX idx_admin_notification_read (read_at),
                INDEX idx_admin_notification_created (created_at),
                INDEX idx_admin_notification_case (case_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $ready = true;
    } catch (Throwable $exception) {
        $ready = false;
    }

    return $ready;
}


// =============================================================================
// SECTION: Create Admin Notification
// =============================================================================
function create_admin_notification(
    string $eventKey,
    string $title,
    string $message,
    string $caseId = '',
    string $technicianId = '',
    string $targetPath = 'admin_portal/smart/dashboard.php'
): void {
    if (!ensure_admin_notifications_table()) return;

    try {
        $stmt = smartops_pdo()->prepare(
            "INSERT INTO admin_notifications
                (event_key, notification_type, title, message, case_id, technician_id, target_path, created_at, read_at)
             VALUES (?, 'case_completed', ?, ?, ?, ?, ?, NOW(), NULL)
             ON DUPLICATE KEY UPDATE
                title=VALUES(title),
                message=VALUES(message),
                technician_id=VALUES(technician_id),
                target_path=VALUES(target_path),
                created_at=NOW(),
                read_at=NULL"
        );
        $stmt->execute([
            trim($eventKey),
            trim($title),
            trim($message),
            trim($caseId),
            trim($technicianId),
            ltrim(trim($targetPath), '/'),
        ]);
    } catch (Throwable $exception) {
        // Notifications must never block the operational completion workflow.
    }
}


// =============================================================================
// SECTION: Fetch Admin Notifications
// =============================================================================
function fetch_admin_notifications(int $limit = 8): array {
    if (!ensure_admin_notifications_table()) return [];
    $limit = max(1, min(20, $limit));

    try {
        return fetch_all(
            "SELECT id, notification_type, title, message, case_id, technician_id, target_path, created_at, read_at
             FROM admin_notifications
             ORDER BY created_at DESC, id DESC
             LIMIT {$limit}"
        );
    } catch (Throwable $exception) {
        return [];
    }
}


// =============================================================================
// SECTION: Admin Unread Notification Count
// =============================================================================
function admin_unread_notification_count(): int {
    if (!ensure_admin_notifications_table()) return 0;
    try {
        return scalar("SELECT COUNT(*) FROM admin_notifications WHERE read_at IS NULL");
    } catch (Throwable $exception) {
        return 0;
    }
}


// =============================================================================
// SECTION: Find Admin Notification
// =============================================================================
function find_admin_notification(int $id): ?array {
    if ($id <= 0 || !ensure_admin_notifications_table()) return null;
    try {
        return fetch_one(
            "SELECT id, title, message, case_id, technician_id, target_path, created_at, read_at
             FROM admin_notifications WHERE id=? LIMIT 1",
            [$id]
        );
    } catch (Throwable $exception) {
        return null;
    }
}


// =============================================================================
// SECTION: Mark Admin Notification Read
// =============================================================================
function mark_admin_notification_read(int $id): void {
    if ($id <= 0 || !ensure_admin_notifications_table()) return;
    try {
        execute_sql("UPDATE admin_notifications SET read_at=COALESCE(read_at, NOW()) WHERE id=?", [$id]);
    } catch (Throwable $exception) {
        // Non-critical UI state only.
    }
}


// =============================================================================
// SECTION: Display Task Time
// =============================================================================
function display_task_time(array $task, string $field): string {
    // Real-time display: only show a timestamp after that exact workflow action is saved.
    // No fallback timestamps are generated, so Accepted/Started/Completed stay "-" until the technician clicks the related action.
    $value = trim((string)($task[$field] ?? ''));
    return $value !== '' ? $value : '-';
}


// =============================================================================
// SECTION: Database Table Exists
// =============================================================================
function db_table_exists(string $table): bool {
    try {
        // Use information_schema instead of SHOW TABLES LIKE ? because some XAMPP/MySQL
        // versions do not handle bound parameters reliably in SHOW statements.
        $stmt = smartops_pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return false;
    }
}


// =============================================================================
// SECTION: Database Column Exists
// =============================================================================
function db_column_exists(string $table, string $column): bool {
    try {
        $stmt = smartops_pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return false;
    }
}


/**
 * Keep Severity and Priority as separate fields in every operational dataset.
 * Existing databases are upgraded automatically; fresh imports already contain
 * these columns in smartops_unified_database.sql.
 */

// =============================================================================
// SECTION: Ensure Severity Priority Columns
// =============================================================================
function ensure_severity_priority_columns(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        if (db_table_exists('cases') && !db_column_exists('cases', 'severity')) {
            execute_sql("ALTER TABLE cases ADD severity VARCHAR(50) NULL AFTER component");
        }
        if (db_table_exists('hitl_cases') && !db_column_exists('hitl_cases', 'priority')) {
            execute_sql("ALTER TABLE hitl_cases ADD priority VARCHAR(50) NULL AFTER severity");
        }
        if (db_table_exists('smart_maintenance_tickets') && !db_column_exists('smart_maintenance_tickets', 'safety_precautions')) {
            execute_sql("ALTER TABLE smart_maintenance_tickets ADD safety_precautions TEXT NULL AFTER possible_root_cause");
        }
        if (db_table_exists('smart_maintenance_tickets') && !db_column_exists('smart_maintenance_tickets', 'verification')) {
            execute_sql("ALTER TABLE smart_maintenance_tickets ADD verification TEXT NULL AFTER safety_precautions");
        }
    } catch (Throwable $exception) {
        // database_ready_message() reports schema issues when migration is not permitted.
    }
}


// =============================================================================
// SECTION: Ensure Technician Level Column
// =============================================================================
function ensure_technician_level_column(): void {
    static $checked = false;
    if ($checked || !db_table_exists('technicians')) return;
    $checked = true;

    try {
        if (!db_column_exists('technicians', 'technician_level')) {
            execute_sql(
                "ALTER TABLE technicians ADD technician_level VARCHAR(20) NOT NULL DEFAULT 'Junior' AFTER role"
            );
        }

        // Migrate the removed legacy role into the independent Senior level.
        execute_sql(
            "UPDATE technicians
             SET technician_level='Senior'
             WHERE LOWER(TRIM(COALESCE(role,''))) IN ('senior technician','senior support technician')"
        );
        execute_sql(
            "UPDATE technicians
             SET role='Technician'
             WHERE LOWER(TRIM(COALESCE(role,''))) IN ('senior technician','senior support technician','ai automation technician')"
        );
        execute_sql(
            "UPDATE technicians
             SET role='Technical Specialist', department='All Departments', category='All Departments'
             WHERE LOWER(TRIM(COALESCE(role,''))) IN ('hitl technician','technical specialist')"
        );
        execute_sql(
            "UPDATE technicians
             SET technician_level='Junior'
             WHERE technician_level IS NULL OR technician_level NOT IN ('Junior','Senior')"
        );
        execute_sql("UPDATE technicians SET technician_level='Junior' WHERE role='Technical Specialist' AND technician_id BETWEEN 'TECH-016' AND 'TECH-020'");
        execute_sql("UPDATE technicians SET technician_level='Senior' WHERE role='Technical Specialist' AND technician_id BETWEEN 'TECH-021' AND 'TECH-025'");
    } catch (Throwable $exception) {
        // database_ready_message() reports schema issues when migration is not permitted.
    }
}


// =============================================================================
// SECTION: Ensure Workflow Support Columns
// =============================================================================
function ensure_workflow_support_columns(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $columns = [
        'workforce_cases' => [
            'case_created_at' => "DATETIME NULL AFTER technician_note",
            'previous_technician_id' => "VARCHAR(20) NULL AFTER case_created_at",
            'support_requested_at' => "DATETIME NULL AFTER previous_technician_id",
            'released_at' => "DATETIME NULL AFTER support_requested_at",
            'transfer_reason' => "VARCHAR(100) NULL AFTER released_at",
            'transfer_requested_at' => "DATETIME NULL AFTER transfer_reason",
            'parts_status' => "VARCHAR(100) NULL AFTER transfer_requested_at",
            'parts_requested_at' => "DATETIME NULL AFTER parts_status",
            'parts_ordered_at' => "DATETIME NULL AFTER parts_requested_at",
            'parts_ready_at' => "DATETIME NULL AFTER parts_ordered_at",
            'outsourcing_status' => "VARCHAR(100) NULL AFTER parts_ready_at",
            'outsourced_at' => "DATETIME NULL AFTER outsourcing_status",
            'assignment_round' => "INT NOT NULL DEFAULT 0 AFTER outsourced_at",
        ],
        'technician_tasks' => [
            'case_created_at' => "DATETIME NULL AFTER assigned_technician_id",
            'previous_technician_id' => "VARCHAR(20) NULL AFTER case_created_at",
            'support_requested_at' => "DATETIME NULL AFTER previous_technician_id",
            'released_at' => "DATETIME NULL AFTER support_requested_at",
            'assignment_round' => "INT NOT NULL DEFAULT 0 AFTER released_at",
        ],
    ];

    try {
        foreach ($columns as $table => $definitions) {
            if (!db_table_exists($table)) continue;
            foreach ($definitions as $column => $definition) {
                if (!db_column_exists($table, $column)) {
                    execute_sql("ALTER TABLE {$table} ADD {$column} {$definition}");
                }
            }
        }

        if (!db_table_exists('task_assignment_history')) {
            execute_sql("CREATE TABLE task_assignment_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                case_id VARCHAR(20) NOT NULL,
                technician_id VARCHAR(20) NOT NULL,
                assignment_round INT NOT NULL DEFAULT 1,
                assigned_at DATETIME NOT NULL,
                accepted_at DATETIME NULL,
                started_at DATETIME NULL,
                ended_at DATETIME NULL,
                end_status VARCHAR(100) NULL,
                event_note TEXT NULL,
                response_minutes INT NULL,
                response_sla_status VARCHAR(50) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_assignment_round (case_id, assignment_round),
                INDEX idx_assignment_technician (technician_id, ended_at),
                INDEX idx_assignment_case (case_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    } catch (Throwable $exception) {
        // database_ready_message() reports schema issues when migration is not permitted.
    }
}


// =============================================================================
// SECTION: Current Assignment History
// =============================================================================
function current_assignment_history(string $caseId, string $technicianId = ''): ?array {
    if (!db_table_exists('task_assignment_history')) return null;
    $sql = "SELECT * FROM task_assignment_history WHERE case_id=? AND ended_at IS NULL";
    $params = [$caseId];
    if ($technicianId !== '') {
        $sql .= " AND technician_id=?";
        $params[] = $technicianId;
    }
    $sql .= " ORDER BY assignment_round DESC, id DESC LIMIT 1";
    return fetch_one($sql, $params);
}


// =============================================================================
// SECTION: Open Assignment History
// =============================================================================
function open_assignment_history(string $caseId, string $technicianId, string $assignedAt, int $round): void {
    if ($caseId === '' || $technicianId === '' || !db_table_exists('task_assignment_history')) return;
    execute_sql(
        "INSERT INTO task_assignment_history (case_id,technician_id,assignment_round,assigned_at)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE technician_id=VALUES(technician_id), assigned_at=VALUES(assigned_at), accepted_at=NULL, started_at=NULL, ended_at=NULL, end_status=NULL, event_note=NULL, response_minutes=NULL, response_sla_status='Pending'",
        [$caseId, $technicianId, max(1, $round), $assignedAt]
    );
}


// =============================================================================
// SECTION: Update Assignment History Action
// =============================================================================
function update_assignment_history_action(string $caseId, string $technicianId, string $action): void {
    $assignment = current_assignment_history($caseId, $technicianId);
    if (!$assignment) return;
    if ($action === 'accept') {
        execute_sql("UPDATE task_assignment_history SET accepted_at=NOW() WHERE id=?", [$assignment['id']]);
    } elseif ($action === 'start') {
        execute_sql("UPDATE task_assignment_history SET started_at=NOW() WHERE id=?", [$assignment['id']]);
    }
}


// =============================================================================
// SECTION: Close Assignment History
// =============================================================================
function close_assignment_history(string $caseId, string $technicianId, string $endStatus, string $note = ''): void {
    $assignment = current_assignment_history($caseId, $technicianId);

    // Self-heal older/demo assignments that existed before assignment-history
    // tracking was introduced. Without this fallback, completing such a task
    // updates technician_tasks but leaves the History screen empty.
    if (!$assignment && db_table_exists('task_assignment_history')) {
        $task = fetch_one(
            "SELECT assignment_round, assigned_time, accepted_time, started_time
             FROM technician_tasks
             WHERE case_id=?
               AND (COALESCE(technician_id,'')=? OR COALESCE(assigned_technician_id,'')=? OR COALESCE(previous_technician_id,'')=?)
             ORDER BY id DESC LIMIT 1",
            [$caseId, $technicianId, $technicianId, $technicianId]
        );

        if ($task) {
            $assignedAt = trim((string)($task['assigned_time'] ?? '')) ?: date('Y-m-d H:i:s');
            $round = max(1, (int)($task['assignment_round'] ?? 1));
            open_assignment_history($caseId, $technicianId, $assignedAt, $round);
            $assignment = current_assignment_history($caseId, $technicianId);

            if ($assignment) {
                execute_sql(
                    "UPDATE task_assignment_history
                     SET accepted_at=COALESCE(accepted_at, NULLIF(?,'')),
                         started_at=COALESCE(started_at, NULLIF(?,''))
                     WHERE id=?",
                    [trim((string)($task['accepted_time'] ?? '')), trim((string)($task['started_time'] ?? '')), $assignment['id']]
                );
            }
        }
    }

    if (!$assignment) return;
    execute_sql(
        "UPDATE task_assignment_history
         SET ended_at=NOW(), end_status=?, event_note=?,
             response_minutes=CASE WHEN accepted_at IS NULL THEN TIMESTAMPDIFF(MINUTE,assigned_at,NOW()) ELSE TIMESTAMPDIFF(MINUTE,assigned_at,accepted_at) END,
             response_sla_status=CASE WHEN TIMESTAMPDIFF(MINUTE,assigned_at,COALESCE(accepted_at,NOW()))>30 THEN 'Breached' WHEN accepted_at IS NULL THEN 'Pending' ELSE 'Within SLA' END
         WHERE id=?",
        [$endStatus, $note, $assignment['id']]
    );
}

/**
 * Backfill completed tasks into task_assignment_history.
 *
 * This repairs demo/legacy tasks completed before history tracking was added,
 * so the technician can immediately see them on the History page.
 */

// =============================================================================
// SECTION: Ensure Completed Assignment History For Technician
// =============================================================================
function ensure_completed_assignment_history_for_technician(string $technicianId): void {
    if ($technicianId === '' || !db_table_exists('task_assignment_history') || !db_table_exists('technician_tasks')) return;

    $completedTasks = fetch_all(
        "SELECT tt.case_id, tt.assignment_round, tt.assigned_time, tt.accepted_time,
                tt.started_time, tt.completed_time, tt.technician_note
         FROM technician_tasks tt
         WHERE tt.task_status='Completed'
           AND tt.completed_time IS NOT NULL
           AND (COALESCE(tt.technician_id,'')=?
                OR COALESCE(tt.assigned_technician_id,'')=?
                OR COALESCE(tt.previous_technician_id,'')=?)
           AND NOT EXISTS (
             SELECT 1 FROM task_assignment_history ah
             WHERE ah.case_id=tt.case_id
               AND ah.technician_id=?
               AND ah.ended_at IS NOT NULL
               AND ah.end_status='Repair Completed'
           )
         ORDER BY tt.completed_time DESC",
        [$technicianId, $technicianId, $technicianId, $technicianId]
    );

    foreach ($completedTasks as $task) {
        $caseId = trim((string)($task['case_id'] ?? ''));
        if ($caseId === '') continue;

        $round = max(1, (int)($task['assignment_round'] ?? 1));
        $completedAt = trim((string)($task['completed_time'] ?? '')) ?: date('Y-m-d H:i:s');
        $assignedAt = trim((string)($task['assigned_time'] ?? '')) ?: $completedAt;
        $acceptedAt = trim((string)($task['accepted_time'] ?? ''));
        $startedAt = trim((string)($task['started_time'] ?? ''));
        $note = trim((string)($task['technician_note'] ?? '')) ?: ('Repair completed by ' . $technicianId . '.');

        execute_sql(
            "INSERT INTO task_assignment_history
                (case_id, technician_id, assignment_round, assigned_at, accepted_at, started_at,
                 ended_at, end_status, event_note, response_minutes, response_sla_status)
             VALUES (?,?,?,?,NULLIF(?,''),NULLIF(?,''),?,'Repair Completed',?,
                     TIMESTAMPDIFF(MINUTE,?,COALESCE(NULLIF(?,''),?)),
                     CASE
                       WHEN NULLIF(?, '') IS NULL THEN 'Pending'
                       WHEN TIMESTAMPDIFF(MINUTE,?,NULLIF(?,''))>30 THEN 'Breached'
                       ELSE 'Within SLA'
                     END)
             ON DUPLICATE KEY UPDATE
                technician_id=VALUES(technician_id),
                assigned_at=VALUES(assigned_at),
                accepted_at=COALESCE(task_assignment_history.accepted_at,VALUES(accepted_at)),
                started_at=COALESCE(task_assignment_history.started_at,VALUES(started_at)),
                ended_at=VALUES(ended_at),
                end_status='Repair Completed',
                event_note=VALUES(event_note),
                response_minutes=VALUES(response_minutes),
                response_sla_status=VALUES(response_sla_status)",
            [
                $caseId, $technicianId, $round, $assignedAt, $acceptedAt, $startedAt,
                $completedAt, $note, $assignedAt, $acceptedAt, $completedAt,
                $acceptedAt, $assignedAt, $acceptedAt,
            ]
        );
    }
}


// =============================================================================
// SECTION: Assign Case To Technician
// =============================================================================
function assign_case_to_technician(string $caseId, string $technicianId, string $supportStatus = '', string $managerNote = ''): bool {
    ensure_workflow_support_columns();
    $technician = find_technician($technicianId);
    if (!$technician || !technician_has_capacity($technicianId, $caseId)) return false;

    $task = fetch_one("SELECT * FROM technician_tasks WHERE case_id=? ORDER BY id DESC LIMIT 1", [$caseId]);
    $work = fetch_one("SELECT * FROM workforce_cases WHERE case_id=? LIMIT 1", [$caseId]);
    if (!$task && !$work) return false;

    $oldId = trim((string)($task['technician_id'] ?? $work['assigned_technician_id'] ?? ''));
    if ($oldId !== '' && $oldId !== $technicianId) {
        close_assignment_history($caseId, $oldId, 'Reassigned', 'Reassigned by manager.');
    }

    $round = max((int)($task['assignment_round'] ?? 0), (int)($work['assignment_round'] ?? 0)) + 1;
    $assignedAt = date('Y-m-d H:i:s');
    $caseCreatedAt = trim((string)($task['case_created_at'] ?? $work['case_created_at'] ?? ''));
    if ($caseCreatedAt === '') {
        $smart = fetch_one("SELECT created_at FROM smart_maintenance_tickets WHERE case_id=?", [$caseId]);
        $caseCreatedAt = trim((string)($smart['created_at'] ?? '')) ?: $assignedAt;
    }

    execute_sql(
        "UPDATE technician_tasks
         SET technician_id=?, assigned_technician_id=?, task_status='Assigned', assigned_time=?, accepted_time=NULL, started_time=NULL, completed_time=NULL,
             response_minutes=NULL, response_sla_status='Pending', repair_minutes=NULL, repair_sla_status='Pending', overall_sla_status='Pending', sla_status='Pending',
             case_created_at=?, assignment_round=?, released_at=NULL,
             support_status=CASE WHEN ?<>'' THEN ? ELSE support_status END
         WHERE case_id=?",
        [$technicianId, $technicianId, $assignedAt, $caseCreatedAt, $round, $supportStatus, $supportStatus, $caseId]
    );
    execute_sql(
        "UPDATE workforce_cases
         SET assigned_technician_id=?, technician_id=?, work_stage='Assigned - Not Started', task_status='Assigned', assigned_at=?, accepted_at=NULL, started_at=NULL, completed_at=NULL,
             response_minutes=NULL, response_sla_status='Pending', repair_minutes=NULL, repair_sla_status='Pending', overall_sla_status='Pending', sla_status='Pending',
             case_created_at=?, assignment_round=?, released_at=NULL,
             support_status=CASE WHEN ?<>'' THEN ? ELSE support_status END,
             manager_note=CASE WHEN ?<>'' THEN ? ELSE manager_note END
         WHERE case_id=?",
        [$technicianId, $technicianId, $assignedAt, $caseCreatedAt, $round, $supportStatus, $supportStatus, $managerNote, $managerNote, $caseId]
    );
    if (db_table_exists('smart_maintenance_tickets')) {
        execute_sql("UPDATE smart_maintenance_tickets SET technician_assigned=?, assigned_to=?, technician_assigned_timestamp=?, ticket_status='Assigned - Not Started' WHERE case_id=?", [$technicianId, $technicianId, $assignedAt, $caseId]);
    }
    open_assignment_history($caseId, $technicianId, $assignedAt, $round);
    if ($oldId !== '' && $oldId !== $technicianId) refresh_technician_summary($oldId);
    refresh_technician_summary($technicianId);
    return true;
}


// =============================================================================
// SECTION: Release Case From Technician
// =============================================================================
function release_case_from_technician(string $caseId, string $technicianId, string $taskStatus, string $supportReason, string $supportStatus, string $note): void {
    ensure_workflow_support_columns();
    close_assignment_history($caseId, $technicianId, match ($supportReason) {
        'Senior Support' => 'Escalated to Senior',
        'Parts Required' => 'Released - Waiting for Parts',
        'Outsourcing' => 'Released - Outsourcing Requested',
        default => 'Released',
    }, $note);

    execute_sql(
        "UPDATE technician_tasks
         SET previous_technician_id=?, technician_id=NULL, assigned_technician_id=NULL, task_status=?, support_reason=?, support_status=?, support_requested_at=NOW(), released_at=NOW(), technician_note=?
         WHERE case_id=?",
        [$technicianId, $taskStatus, $supportReason, $supportStatus, $note, $caseId]
    );
    execute_sql(
        "UPDATE workforce_cases
         SET previous_technician_id=?, assigned_technician_id=NULL, technician_id=NULL, work_stage=?, task_status=?, support_reason=?, support_status=?, support_requested_at=NOW(), released_at=NOW(), technician_note=?
         WHERE case_id=?",
        [$technicianId, $taskStatus, $taskStatus, $supportReason, $supportStatus, $note, $caseId]
    );
    if (db_table_exists('smart_maintenance_tickets')) {
        execute_sql("UPDATE smart_maintenance_tickets SET technician_assigned=NULL, assigned_to=NULL, support_reason=?, support_status=?, ticket_status=? WHERE case_id=?", [$supportReason, $supportStatus, $taskStatus, $caseId]);
    }
    refresh_technician_summary($technicianId);
}


// =============================================================================
// SECTION: Available Candidate For Case
// =============================================================================
function available_candidate_for_case(array $case, string $preferredTechnicianId = ''): ?array {
    $previousId = $preferredTechnicianId !== '' ? $preferredTechnicianId : trim((string)($case['previous_technician_id'] ?? ''));
    $previous = $previousId !== '' ? find_technician($previousId) : null;
    $role = (string)($previous['role'] ?? 'Technician');
    $department = normalize_technician_department((string)($previous['department'] ?? $case['hotel_asset'] ?? ''));

    if ($previous && technician_has_capacity($previousId) && !str_contains(strtolower((string)($previous['status'] ?? '')), 'leave')) {
        return $previous;
    }

    $sql = "SELECT t.* FROM technicians t
            LEFT JOIN (" . technician_active_task_sql() . ") open_tasks ON open_tasks.technician_id=t.technician_id
            WHERE t.role=? AND " . technician_assignable_status_sql() . " AND COALESCE(open_tasks.active_tasks,0)=0";
    $params = [$role];
    if ($role !== 'Technical Specialist') {
        $sql .= " AND COALESCE(NULLIF(t.department,''),t.category)=?";
        $params[] = $department;
    }
    $sql .= " ORDER BY t.technician_level='Senior', t.technician_id LIMIT 1";
    return fetch_one($sql, $params);
}


// =============================================================================
// SECTION: Auto Reassign Case
// =============================================================================
function auto_reassign_case(string $caseId, string $supportStatus): ?string {
    $case = fetch_one("SELECT * FROM workforce_cases WHERE case_id=?", [$caseId]);
    if (!$case) return null;
    $candidate = available_candidate_for_case($case);
    if (!$candidate) return null;
    return assign_case_to_technician($caseId, (string)$candidate['technician_id'], $supportStatus) ? (string)$candidate['technician_id'] : null;
}

/**
 * Support records that still need a manager action in the existing Workforce page.
 * Assigned Senior/parts cases are excluded so the support chart never double-counts
 * an active work order that is already back with a technician.
 */

// =============================================================================
// SECTION: Workforce Manager Action Where
// =============================================================================
function workforce_manager_action_where(string $alias = ''): string {
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    return "{$prefix}work_stage<>'Completed' AND ("
        . "({$prefix}support_reason='Senior Support' AND {$prefix}work_stage='Pending Senior Assignment') OR "
        . "({$prefix}support_reason='Parts Required' AND {$prefix}work_stage IN ('Waiting for Parts','Pending Reassignment')) OR "
        . "({$prefix}support_reason='Outsourcing' AND {$prefix}work_stage IN ('Pending Outsourcing','Outsourced','Pending Reassignment'))"
        . ")";
}


// =============================================================================
// SECTION: Workforce Case Needs Manager Action
// =============================================================================
function workforce_case_needs_manager_action(array $case): bool {
    $reason = trim((string)($case['support_reason'] ?? ''));
    $stage = trim((string)($case['work_stage'] ?? ''));
    return match ($reason) {
        'Senior Support' => $stage === 'Pending Senior Assignment',
        'Parts Required' => in_array($stage, ['Waiting for Parts','Pending Reassignment'], true),
        'Outsourcing' => in_array($stage, ['Pending Outsourcing','Outsourced','Pending Reassignment'], true),
        default => false,
    };
}


// =============================================================================
// SECTION: Ensure Technician Task Trigger Column
// =============================================================================
function ensure_technician_task_trigger_column(): void {
    static $checked = false;
    if ($checked || !db_table_exists('technician_tasks')) return;
    $checked = true;

    try {
        ensure_hitl_dataset_consistency();
        if (!db_column_exists('technician_tasks', 'escalation_trigger')) {
            execute_sql(
                "ALTER TABLE technician_tasks ADD escalation_trigger VARCHAR(50) NULL AFTER severity"
            );
        }

        $rows = fetch_all(
            "SELECT tt.id, tt.severity, tt.priority, tt.escalation_trigger,
                    hc.hitl_reason, hc.severity AS hitl_severity, hc.priority AS hitl_priority,
                    sm.ai_confidence
             FROM technician_tasks tt
             JOIN hitl_cases hc ON hc.case_id=tt.case_id
             LEFT JOIN smart_maintenance_tickets sm ON sm.case_id=tt.case_id"
        );

        foreach ($rows as $row) {
            $classificationRow = $row;
            if (trim((string)($row['hitl_severity'] ?? '')) !== '') {
                $classificationRow['severity'] = $row['hitl_severity'];
            }
            if (trim((string)($row['hitl_priority'] ?? '')) !== '') {
                $classificationRow['priority'] = $row['hitl_priority'];
            }

            $expected = hitl_primary_escalation_trigger($classificationRow);
            if (trim((string)($row['escalation_trigger'] ?? '')) !== $expected) {
                execute_sql(
                    "UPDATE technician_tasks SET escalation_trigger=? WHERE id=?",
                    [$expected, $row['id']]
                );
            }
        }
    } catch (Throwable $exception) {
        // database_ready_message() reports schema issues when migration is not permitted.
    }
}


// =============================================================================
// SECTION: Ensure HITL Dataset Consistency
// =============================================================================
function ensure_hitl_dataset_consistency(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    ensure_severity_priority_columns();
    ensure_workflow_support_columns();

    foreach (['hitl_cases','smart_maintenance_tickets','cases','workforce_cases','technician_tasks'] as $table) {
        if (!db_table_exists($table)) return;
    }

    try {
        // Display missing/placeholder asset values consistently.
        foreach (['hitl_cases', 'smart_maintenance_tickets', 'cases', 'workforce_cases', 'technician_tasks'] as $table) {
            execute_sql(
                "UPDATE {$table}
                 SET hotel_asset='Other'
                 WHERE hotel_asset IS NULL
                    OR TRIM(hotel_asset)=''
                    OR LOWER(TRIM(hotel_asset)) IN ('-','unknown','none','null','n/a','na','out_of_kb','out of kb','out of knowledge base')"
            );
        }

        // Remove D-code prefixes and migrate old aliases.
        foreach (['hitl_cases', 'smart_maintenance_tickets', 'cases', 'workforce_cases', 'technician_tasks'] as $table) {
            execute_sql(
                "UPDATE {$table}
                 SET hotel_asset=TRIM(SUBSTRING(TRIM(hotel_asset), LOCATE(' ', TRIM(hotel_asset)) + 1))
                 WHERE TRIM(hotel_asset) REGEXP '^D[0-9]+[[:space:]]+'"
            );
            execute_sql(
                "UPDATE {$table}
                 SET hotel_asset=CASE
                     WHEN LOWER(TRIM(hotel_asset)) IN ('fire','fire system') THEN 'Fire Protection'
                     WHEN LOWER(TRIM(hotel_asset)) IN ('elevator','elevator & lifts','lifts') THEN 'Conveying'
                     WHEN LOWER(TRIM(hotel_asset))='hvac' THEN 'HVAC'
                     WHEN LOWER(TRIM(hotel_asset))='plumbing' THEN 'Plumbing'
                     WHEN LOWER(TRIM(hotel_asset))='electrical' THEN 'Electrical'
                     ELSE hotel_asset
                 END"
            );
        }

        // Remove P1/P2/P3/P4 codes while preserving the business label.
        foreach (['cases' => 'priority', 'hitl_cases' => 'priority', 'workforce_cases' => 'priority', 'technician_tasks' => 'priority', 'smart_maintenance_tickets' => 'priority_level'] as $table => $column) {
            execute_sql(
                "UPDATE {$table}
                 SET {$column}=CASE
                     WHEN {$column} IS NULL OR TRIM({$column})='' THEN {$column}
                     WHEN UPPER(REPLACE(REPLACE(TRIM({$column}),'_',' '),'-',' ')) LIKE 'P1 %'
                       OR LOWER(TRIM({$column})) LIKE '%immediate%'
                       OR LOWER(TRIM({$column}))='critical' THEN 'Immediate'
                     WHEN UPPER(REPLACE(REPLACE(TRIM({$column}),'_',' '),'-',' ')) LIKE 'P2 %'
                       OR LOWER(TRIM({$column})) LIKE '%urgent%'
                       OR LOWER(TRIM({$column}))='high' THEN 'Urgent'
                     WHEN UPPER(REPLACE(REPLACE(TRIM({$column}),'_',' '),'-',' ')) LIKE 'P3 %'
                       OR LOWER(TRIM({$column})) LIKE '%standard%'
                       OR LOWER(TRIM({$column}))='medium' THEN 'Standard'
                     WHEN UPPER(REPLACE(REPLACE(TRIM({$column}),'_',' '),'-',' ')) LIKE 'P4 %'
                       OR LOWER(TRIM({$column}))='low' THEN 'Low'
                     ELSE {$column}
                 END"
            );
        }

        // Restore classification from any surviving source. Low Confidence is a
        // review reason only and must not remove Category, Severity or Priority.
        execute_sql(
            "UPDATE hitl_cases hc
             LEFT JOIN smart_maintenance_tickets sm ON sm.case_id=hc.case_id
             LEFT JOIN cases c ON c.case_id=hc.case_id
             LEFT JOIN workforce_cases w ON w.case_id=hc.case_id
             LEFT JOIN technician_tasks tt ON tt.case_id=hc.case_id
             SET hc.hotel_asset=COALESCE(NULLIF(sm.hotel_asset,''),NULLIF(c.hotel_asset,''),NULLIF(w.hotel_asset,''),NULLIF(tt.hotel_asset,''),NULLIF(hc.hotel_asset,''),'Other'),
                 hc.component=COALESCE(NULLIF(sm.component,''),NULLIF(c.component,''),NULLIF(w.component,''),NULLIF(tt.component,''),NULLIF(hc.component,'')),
                 hc.severity=COALESCE(NULLIF(hc.severity,''),NULLIF(sm.severity_level,''),NULLIF(c.severity,''),NULLIF(w.severity,''),NULLIF(tt.severity,'')),
                 hc.priority=COALESCE(NULLIF(hc.priority,''),NULLIF(sm.priority_level,''),NULLIF(c.priority,''),NULLIF(w.priority,''),NULLIF(tt.priority,'')),
                 hc.failure_mode=COALESCE(NULLIF(sm.failure_mode,''),NULLIF(tt.failure_mode,''),NULLIF(hc.failure_mode,''),hc.component),
                 hc.observed_symptoms=COALESCE(NULLIF(sm.observed_symptoms,''),NULLIF(tt.observed_symptoms,''),NULLIF(hc.observed_symptoms,''),hc.issue_summary),
                 hc.possible_root_cause=COALESCE(NULLIF(sm.possible_root_cause,''),NULLIF(tt.possible_root_cause,''),NULLIF(hc.possible_root_cause,''),'Requires technician diagnosis')
             WHERE LOWER(TRIM(COALESCE(hc.hitl_reason,''))) NOT LIKE '%out of knowledge%'"
        );

        // Recover one classification value from the other when possible.
        execute_sql(
            "UPDATE hitl_cases
             SET severity=CASE
                 WHEN LOWER(TRIM(COALESCE(priority,'')))='immediate' THEN 'Critical'
                 WHEN LOWER(TRIM(COALESCE(priority,'')))='urgent' THEN 'High'
                 WHEN LOWER(TRIM(COALESCE(priority,'')))='standard' THEN 'Medium'
                 WHEN LOWER(TRIM(COALESCE(priority,'')))='low' THEN 'Low'
                 ELSE severity END
             WHERE severity IS NULL OR TRIM(severity)=''"
        );
        execute_sql(
            "UPDATE hitl_cases
             SET priority=CASE
                 WHEN LOWER(TRIM(COALESCE(severity,'')))='critical' THEN 'Immediate'
                 WHEN LOWER(TRIM(COALESCE(severity,'')))='high' THEN 'Urgent'
                 WHEN LOWER(TRIM(COALESCE(severity,'')))='medium' THEN 'Standard'
                 WHEN LOWER(TRIM(COALESCE(severity,'')))='low' THEN 'Low'
                 ELSE priority END
             WHERE priority IS NULL OR TRIM(priority)=''"
        );

        // Preserve Low Confidence as the manager-facing review reason. The
        // primary HITL queue is derived separately by hitl_primary_escalation_trigger().
        execute_sql(
            "UPDATE hitl_cases hc
             LEFT JOIN smart_maintenance_tickets sm ON sm.case_id=hc.case_id
             SET hc.hitl_reason = CASE
                 WHEN LOWER(TRIM(COALESCE(hc.hitl_reason,''))) LIKE '%out of knowledge%'
                   THEN 'Out of Knowledge Base'
                 WHEN LOWER(TRIM(COALESCE(hc.hitl_reason,''))) LIKE '%low confidence%'
                   THEN 'Low Confidence'
                 WHEN LOWER(TRIM(COALESCE(hc.severity,''))) LIKE '%critical%'
                   OR LOWER(TRIM(COALESCE(hc.priority,'')))='immediate'
                   OR LOWER(TRIM(COALESCE(hc.hitl_reason,''))) LIKE '%critical%'
                   THEN 'Critical'
                 WHEN LOWER(TRIM(COALESCE(hc.severity,''))) LIKE '%high%'
                   OR LOWER(TRIM(COALESCE(hc.priority,'')))='urgent'
                   OR LOWER(TRIM(COALESCE(hc.hitl_reason,''))) LIKE '%high%'
                   OR LOWER(TRIM(COALESCE(hc.hitl_reason,''))) LIKE '%urgent%'
                   THEN 'High'
                 WHEN NULLIF(TRIM(COALESCE(sm.ai_confidence,'')),'') IS NOT NULL
                   AND (
                     CASE
                       WHEN CAST(REPLACE(sm.ai_confidence,'%','') AS DECIMAL(10,4)) <= 1
                         THEN CAST(REPLACE(sm.ai_confidence,'%','') AS DECIMAL(10,4)) * 100
                       ELSE CAST(REPLACE(sm.ai_confidence,'%','') AS DECIMAL(10,4))
                     END
                   ) < 75
                   THEN 'Low Confidence'
                 ELSE 'Low Confidence'
             END"
        );

        // OOKB has no validated classification.
        execute_sql(
            "UPDATE hitl_cases
             SET hotel_asset='Other', component=NULL, severity=NULL, priority=NULL,
                 failure_mode=NULL, possible_root_cause=NULL
             WHERE hitl_reason='Out of Knowledge Base'"
        );

        // Synchronise the retained classification to all operational tables.
        execute_sql(
            "UPDATE cases c
             JOIN hitl_cases hc ON hc.case_id=c.case_id
             SET c.hotel_asset=hc.hotel_asset,
                 c.component=hc.component,
                 c.severity=hc.severity,
                 c.priority=hc.priority"
        );
        execute_sql(
            "UPDATE smart_maintenance_tickets sm
             JOIN hitl_cases hc ON hc.case_id=sm.case_id
             SET sm.hotel_asset=hc.hotel_asset,
                 sm.component=hc.component,
                 sm.severity_level=hc.severity,
                 sm.priority_level=hc.priority"
        );
        execute_sql(
            "UPDATE workforce_cases w
             JOIN hitl_cases hc ON hc.case_id=w.case_id
             SET w.hotel_asset=hc.hotel_asset,
                 w.component=hc.component,
                 w.severity=hc.severity,
                 w.priority=hc.priority"
        );

        // Guidance can remain restricted while classification stays visible.
        execute_sql(
            "UPDATE smart_maintenance_tickets sm
             JOIN hitl_cases hc ON hc.case_id=sm.case_id
             SET sm.corrective_action=NULL,
                 sm.preventive_maintenance=NULL,
                 sm.verification=NULL
             WHERE hc.hitl_reason IN ('Low Confidence','Out of Knowledge Base')"
        );

        execute_sql(
            "UPDATE technician_tasks tt
             JOIN hitl_cases hc ON hc.case_id=tt.case_id
             LEFT JOIN smart_maintenance_tickets sm ON sm.case_id=tt.case_id
             SET tt.escalation_trigger=CASE
                     WHEN LOWER(TRIM(COALESCE(hc.severity,'')))='critical'
                       OR LOWER(TRIM(COALESCE(hc.priority,'')))='immediate' THEN 'Critical'
                     WHEN LOWER(TRIM(COALESCE(hc.severity,'')))='high'
                       OR LOWER(TRIM(COALESCE(hc.priority,'')))='urgent' THEN 'High'
                     ELSE 'Low Confidence' END,
                 tt.hotel_asset=hc.hotel_asset,
                 tt.component=hc.component,
                 tt.severity=hc.severity,
                 tt.priority=hc.priority,
                 tt.failure_mode=COALESCE(NULLIF(hc.failure_mode,''),NULLIF(sm.failure_mode,''),tt.failure_mode),
                 tt.observed_symptoms=COALESCE(NULLIF(hc.observed_symptoms,''),NULLIF(sm.observed_symptoms,''),tt.observed_symptoms),
                 tt.possible_root_cause=COALESCE(NULLIF(hc.possible_root_cause,''),NULLIF(sm.possible_root_cause,''),tt.possible_root_cause)
             WHERE hc.hitl_reason <> 'Out of Knowledge Base'"
        );

        execute_sql(
            "UPDATE technician_tasks tt
             JOIN hitl_cases hc ON hc.case_id=tt.case_id
             SET tt.corrective_action=NULL,
                 tt.preventive_maintenance=NULL,
                 tt.verification=NULL
             WHERE hc.hitl_reason IN ('Low Confidence','Out of Knowledge Base')"
        );

        execute_sql(
            "UPDATE technician_tasks tt
             JOIN hitl_cases hc ON hc.case_id=tt.case_id
             SET tt.escalation_trigger='Out of Knowledge Base',
                 tt.hotel_asset='Other',
                 tt.component=NULL,
                 tt.severity=NULL,
                 tt.priority=NULL,
                 tt.failure_mode=NULL,
                 tt.possible_root_cause=NULL
             WHERE hc.hitl_reason='Out of Knowledge Base'"
        );
    } catch (Throwable $exception) {
        // database_ready_message() reports schema issues when migration is not permitted.
    }
}


// =============================================================================
// SECTION: Technician Level Label
// =============================================================================
function technician_level_label(?array $technician): string {
    $level = ucfirst(strtolower(trim((string)($technician['technician_level'] ?? ''))));
    return in_array($level, ['Junior', 'Senior'], true) ? $level : 'Junior';
}


// =============================================================================
// SECTION: Support Asset Group
// =============================================================================
function support_asset_group(?string $asset): string {
    $clean = clean_asset_name($asset);
    $lower = strtolower($clean);
    if (str_contains($lower, 'fire')) return 'Fire Protection';
    if (str_contains($lower, 'elevator') || str_contains($lower, 'convey')) return 'Conveying';
    if (str_contains($lower, 'hvac')) return 'HVAC';
    if (str_contains($lower, 'plumb')) return 'Plumbing';
    if (str_contains($lower, 'electric')) return 'Electrical';
    return 'Other';
}


// =============================================================================
// SECTION: Support External Services Catalog
// =============================================================================
function support_external_services_catalog(): array {
    return [
        'HVAC' => ['HVAC Specialist','Chiller Contractor','Refrigeration Contractor','Ventilation Specialist','Building Automation System Vendor','Other'],
        'Plumbing' => ['Plumbing Contractor','Pipe Replacement Specialist','Water Pump Contractor','Drainage Specialist','Water Treatment Contractor','Other'],
        'Electrical' => ['Licensed Electrician','Switchboard Contractor','Generator Specialist','UPS Specialist','Electrical Testing Contractor','Other'],
        'Fire Protection' => ['Fire Protection Contractor','Fire Alarm Vendor','Sprinkler System Contractor','Fire Extinguisher Service','Fire Safety Inspection Company','Other'],
        'Conveying' => ['Elevator Manufacturer','Lift Maintenance Contractor','Escalator Specialist','Elevator Safety Inspector','Lift Control System Vendor','Other'],
        'Other' => ['General Maintenance Contractor','Building Services Contractor','Certified Technical Contractor','Equipment Manufacturer','Specialist Inspection Company','Other'],
    ];
}


// =============================================================================
// SECTION: Support Outsourcing Reasons Catalog
// =============================================================================
function support_outsourcing_reasons_catalog(): array {
    return [
        'HVAC' => ['Specialist Equipment Required','Manufacturer or Warranty Work','Certified Contractor Required','Major System Overhaul','Work Outside Internal Capability','Other'],
        'Plumbing' => ['Major Pipe Replacement','Specialist Drainage Equipment Required','Certified Water System Work','External Pump Repair Required','Work Outside Internal Capability','Other'],
        'Electrical' => ['Licensed Work Required','Specialist Testing Required','Major Switchboard Work','Generator or UPS Servicing','Work Outside Internal Capability','Other'],
        'Fire Protection' => ['Certification Required','Regulatory Testing Required','Specialist Commissioning Required','Major Fire System Repair','Work Outside Internal Capability','Other'],
        'Conveying' => ['Licensed Contractor Required','Statutory Inspection Required','Manufacturer Specialist Required','Major Lift System Repair','Work Outside Internal Capability','Other'],
        'Other' => ['Specialist Equipment Required','Certified Contractor Required','Manufacturer Support Required','Major Repair Required','Work Outside Internal Capability','Other'],
    ];
}


// =============================================================================
// SECTION: Support Parts Catalog
// =============================================================================
function support_parts_catalog(): array {
    return [
        'HVAC' => ['Air Filter','Thermostat','Fan Motor','Compressor Relay','Refrigerant Valve','Other'],
        'Plumbing' => ['Pipe Fitting','Seal or Gasket','Water Pump','Drain Trap','Control Valve','Other'],
        'Electrical' => ['Circuit Breaker','Fuse','Contactor','Cable or Wiring','Emergency Light','Other'],
        'Fire Protection' => ['Smoke Detector','Alarm Module','Sprinkler Head','Fire Extinguisher','Control Valve','Other'],
        'Conveying' => ['Door Sensor','Control Relay','Drive Belt','Brake Pad','Guide Roller','Other'],
        'Other' => ['Sensor','Relay','Fastener Kit','Seal or Gasket','Generic Spare Part','Other'],
    ];
}


// =============================================================================
// SECTION: Database Ready Message
// =============================================================================
function database_ready_message(): string {
    ensure_severity_priority_columns();
    ensure_technician_level_column();
    ensure_workflow_support_columns();
    ensure_technician_task_trigger_column();
    $checks = [
        ['technicians','technician_id'],
        ['technicians','warning_reason'],
        ['technicians','is_active'],
        ['technicians','technician_level'],
        ['cases','severity'],
        ['workforce_cases','case_id'],
        ['workforce_cases','support_status'],
        ['workforce_cases','previous_technician_id'],
        ['technician_tasks','previous_technician_id'],
        ['task_assignment_history','case_id'],
        ['hitl_cases','case_id'],
        ['hitl_cases','priority'],
        ['technician_tasks','case_id'],
        ['technician_tasks','escalation_trigger'],
        ['cases','case_id'],
    ];
    foreach ($checks as [$table, $column]) {
        if (!db_table_exists($table)) return "Missing table: {$table}. Please import phpmyadmin_database/smartops_unified_database.sql in phpMyAdmin.";
        if (!db_column_exists($table, $column)) return "Missing column: {$table}.{$column}. Please re-import phpmyadmin_database/smartops_unified_database.sql.";
    }
    return '';
}


/**
 * Recalculate live SLA values using Malaysia database time.
 * Acceptance target: 30 minutes from assignment.
 * End-to-end target: 90 minutes from original case creation to final completion, including support waiting time.
 */

// =============================================================================
// SECTION: Refresh Live SLA Statuses
// =============================================================================
function refresh_live_sla_statuses(): void {
    ensure_workflow_support_columns();
    if (!db_table_exists('technician_tasks') || !db_table_exists('workforce_cases')) return;

    $pdo = smartops_pdo();
    try {
        $pdo->beginTransaction();

        // Every assignment has its own 30-minute acceptance SLA.
        // A breach from an earlier assignment round remains attached to the case.
        if (db_table_exists('task_assignment_history')) {
            execute_sql(
                "UPDATE task_assignment_history
                 SET response_minutes=TIMESTAMPDIFF(MINUTE,assigned_at,COALESCE(accepted_at,ended_at,NOW())),
                     response_sla_status=CASE
                       WHEN TIMESTAMPDIFF(MINUTE,assigned_at,COALESCE(accepted_at,ended_at,NOW()))>30 THEN 'Breached'
                       WHEN accepted_at IS NULL AND ended_at IS NULL THEN 'Pending'
                       WHEN accepted_at IS NULL THEN 'Not Accepted'
                       ELSE 'Within SLA' END"
            );
        }

        $historyBreachSql = db_table_exists('task_assignment_history')
            ? "EXISTS (SELECT 1 FROM task_assignment_history ah WHERE ah.case_id=tt.case_id AND ah.response_sla_status='Breached')"
            : "0=1";

        execute_sql(
            "UPDATE technician_tasks tt
             LEFT JOIN smart_maintenance_tickets sm ON sm.case_id=tt.case_id
             SET tt.case_created_at=COALESCE(tt.case_created_at, sm.created_at, NOW()),
                 tt.response_minutes=CASE
                   WHEN tt.assigned_time IS NULL THEN NULL
                   ELSE TIMESTAMPDIFF(MINUTE,tt.assigned_time,COALESCE(tt.accepted_time,NOW())) END,
                 tt.response_sla_status=CASE
                   WHEN {$historyBreachSql} THEN 'Breached'
                   WHEN tt.assigned_time IS NULL THEN 'Pending'
                   WHEN TIMESTAMPDIFF(MINUTE,tt.assigned_time,COALESCE(tt.accepted_time,NOW()))>COALESCE(tt.response_target_minutes,30) THEN 'Breached'
                   WHEN tt.accepted_time IS NULL THEN 'Pending'
                   ELSE 'Within SLA' END,
                 tt.repair_minutes=TIMESTAMPDIFF(MINUTE,COALESCE(tt.case_created_at,sm.created_at,NOW()),COALESCE(tt.completed_time,NOW())),
                 tt.repair_sla_status=CASE
                   WHEN TIMESTAMPDIFF(MINUTE,COALESCE(tt.case_created_at,sm.created_at,NOW()),COALESCE(tt.completed_time,NOW()))>COALESCE(tt.repair_target_minutes,90) THEN 'Breached'
                   WHEN tt.task_status='Completed' THEN 'Within SLA'
                   ELSE 'Pending' END"
        );

        execute_sql(
            "UPDATE technician_tasks
             SET overall_sla_status=CASE
                   WHEN response_sla_status='Breached' OR repair_sla_status='Breached' THEN 'Breached'
                   WHEN task_status='Completed' THEN 'Within SLA'
                   ELSE 'Pending' END,
                 sla_status=CASE
                   WHEN response_sla_status='Breached' THEN 'Response Breach'
                   WHEN repair_sla_status='Breached' THEN 'Repair Breach'
                   WHEN task_status='Completed' THEN 'Within SLA'
                   ELSE 'Pending' END"
        );

        execute_sql(
            "UPDATE workforce_cases w
             INNER JOIN technician_tasks tt ON tt.case_id=w.case_id
             SET w.case_created_at=tt.case_created_at,
                 w.assigned_at=tt.assigned_time,
                 w.accepted_at=tt.accepted_time,
                 w.started_at=tt.started_time,
                 w.completed_at=tt.completed_time,
                 w.response_minutes=tt.response_minutes,
                 w.response_sla_status=tt.response_sla_status,
                 w.repair_minutes=tt.repair_minutes,
                 w.repair_sla_status=tt.repair_sla_status,
                 w.overall_sla_status=tt.overall_sla_status,
                 w.sla_status=tt.sla_status"
        );

        if (db_table_exists('smart_maintenance_tickets')) {
            execute_sql(
                "UPDATE smart_maintenance_tickets sm
                 INNER JOIN technician_tasks tt ON tt.case_id=sm.case_id
                 SET sm.actual_time=CONCAT(COALESCE(tt.repair_minutes,0),' min'),
                     sm.sla_status=tt.sla_status,
                     sm.completed_date=CASE WHEN tt.task_status='Completed' THEN tt.completed_time ELSE sm.completed_date END"
            );
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}


// =============================================================================
// SECTION: Refresh All Technician Summaries
// =============================================================================
function refresh_all_technician_summaries(): void {
    if (!db_table_exists('technicians')) return;
    foreach (fetch_all("SELECT technician_id FROM technicians WHERE is_active=1") as $row) {
        refresh_technician_summary((string)$row['technician_id']);
    }
}


// =============================================================================
// SECTION: Technician Management CSRF Token
// =============================================================================
function technician_management_csrf_token(): string {
    if (empty($_SESSION['technician_management_csrf'])) {
        $_SESSION['technician_management_csrf'] = bin2hex(random_bytes(24));
    }
    return (string)$_SESSION['technician_management_csrf'];
}


// =============================================================================
// SECTION: Verify Technician Management CSRF
// =============================================================================
function verify_technician_management_csrf(?string $token): bool {
    $expected = (string)($_SESSION['technician_management_csrf'] ?? '');
    return $expected !== '' && is_string($token) && hash_equals($expected, $token);
}


// =============================================================================
// SECTION: Next Technician ID
// =============================================================================
function next_technician_id(): string {
    $last = fetch_one("SELECT technician_id FROM technicians ORDER BY CAST(SUBSTRING_INDEX(technician_id,'-',-1) AS UNSIGNED) DESC LIMIT 1");
    $number = (int)preg_replace('/\D+/', '', (string)($last['technician_id'] ?? '')) + 1;
    return 'TECH-' . str_pad((string)max(1, $number), 3, '0', STR_PAD_LEFT);
}


// =============================================================================
// SECTION: Active Technician Count
// =============================================================================
function active_technician_count(): int {
    return scalar("SELECT COUNT(*) FROM technicians WHERE is_active=1");
}


// =============================================================================
// SECTION: Is SLA Breach
// =============================================================================
function is_sla_breach(array $row): bool {
    $status = strtolower(trim((string)($row['sla_status'] ?? '')));
    return in_array($status, ['response breach','repair breach','breach','violated','sla violation'], true)
        || strtolower(trim((string)($row['response_sla_status'] ?? ''))) === 'breached'
        || strtolower(trim((string)($row['repair_sla_status'] ?? ''))) === 'breached';
}


// =============================================================================
// SECTION: Priority Display Name
// =============================================================================
function priority_display_name(?string $priority): string {
    $raw = trim((string)$priority);
    if ($raw === '') return '-';

    $normalized = strtolower(str_replace(['_', '-'], ' ', $raw));
    $normalized = preg_replace('/\s+/', ' ', $normalized);

    if (preg_match('/\bp1\b/', $normalized) || str_contains($normalized, 'immediate') || str_contains($normalized, 'critical')) {
        return 'Immediate';
    }
    if (preg_match('/\bp2\b/', $normalized) || str_contains($normalized, 'urgent') || str_contains($normalized, 'high')) {
        return 'Urgent';
    }
    if (preg_match('/\bp3\b/', $normalized) || str_contains($normalized, 'standard') || str_contains($normalized, 'medium')) {
        return 'Standard';
    }
    if (preg_match('/\bp4\b/', $normalized) || str_contains($normalized, 'low')) {
        return 'Low';
    }

    return ucwords($normalized);
}


// =============================================================================
// SECTION: Priority Class
// =============================================================================
function priority_class(?string $priority): string {
    $display = strtolower(priority_display_name($priority));
    if (in_array($display, ['immediate', 'urgent'], true)) return 'priority-high';
    if ($display === 'standard') return 'priority-medium';
    if ($display === 'low') return 'priority-low';
    return 'priority-normal';
}

/** Render a consistent severity pill across every Admin dashboard. */

// =============================================================================
// SECTION: Severity Badge Html
// =============================================================================
function severity_badge_html(?string $severity): string {
    $display = trim((string)$severity);
    if ($display === '' || $display === '-') return '-';

    $normalized = strtolower(str_replace(['_', '-'], ' ', $display));
    $class = 'severity-normal';
    if (str_contains($normalized, 'critical')) $class = 'severity-critical';
    elseif (str_contains($normalized, 'high')) $class = 'severity-high';
    elseif (str_contains($normalized, 'medium')) $class = 'severity-medium';
    elseif (str_contains($normalized, 'low')) $class = 'severity-low';

    return '<span class="severity-badge ' . $class . '">' . e($display) . '</span>';
}


// =============================================================================
// SECTION: HITL Queue Label
// =============================================================================
function hitl_queue_label(array $row): string {
    return match (hitl_primary_escalation_trigger($row)) {
        'Critical' => 'Immediate',
        'High' => 'Urgent',
        'Low Confidence' => 'Low Confidence',
        'Out of Knowledge Base' => 'Out of Knowledge Base',
        default => 'Human Review',
    };
}


// =============================================================================
// SECTION: Stage Class
// =============================================================================
function stage_class(?string $stage): string {
    $s = strtolower((string)$stage);
    if (str_contains($s, 'completed')) return 'stage-completed';
    if (str_contains($s, 'reassign') || str_contains($s, 'rework')) return 'stage-warning';
    if (str_contains($s, 'waiting') || str_contains($s, 'pending') || str_contains($s, 'outsourc')) return 'stage-warning';
    if (str_contains($s, 'support') || str_contains($s, 'progress') || str_contains($s, 'assigned')) return 'stage-active';
    return 'stage-pending';
}


// =============================================================================
// SECTION: Find Technician
// =============================================================================
function find_technician(string $id): ?array {
    if ($id === '') return null;
    return fetch_one('SELECT * FROM technicians WHERE technician_id = ? AND is_active=1', [$id]);
}


// =============================================================================
// SECTION: Technician Active Task SQL
// =============================================================================
function technician_active_task_sql(): string {
    return "SELECT technician_id, COUNT(*) AS active_tasks
            FROM technician_tasks
            WHERE task_status IN ('Assigned','Waiting Technician Acceptance','Accepted','In Progress','Repair In Progress','Started')
              AND technician_id IS NOT NULL AND technician_id<>''
            GROUP BY technician_id";
}


// =============================================================================
// SECTION: Technician Assignable Status SQL
// =============================================================================
function technician_assignable_status_sql(): string {
    return "(t.is_active=1 AND LOWER(COALESCE(t.status,'')) NOT LIKE '%leave%' AND LOWER(COALESCE(t.status,'')) NOT LIKE '%resign%')";
}


// =============================================================================
// SECTION: Technician Open Task Expr
// =============================================================================
function technician_open_task_expr(): string {
    return "COALESCE(open_tasks.active_tasks, 0)";
}


// =============================================================================
// SECTION: Technician Active Task Count
// =============================================================================
function technician_active_task_count(string $technicianId, string $excludeCaseId = ''): int {
    if ($technicianId === '') return 0;
    $sql = "SELECT COUNT(*) FROM technician_tasks WHERE technician_id=? AND task_status IN ('Assigned','Waiting Technician Acceptance','Accepted','In Progress','Repair In Progress','Started')";
    $params = [$technicianId];
    if ($excludeCaseId !== '') { $sql .= " AND case_id<>?"; $params[] = $excludeCaseId; }
    return scalar($sql, $params);
}


// =============================================================================
// SECTION: Technician Has Capacity
// =============================================================================
function technician_has_capacity(string $technicianId, string $excludeCaseId = ''): bool {
    return technician_active_task_count($technicianId, $excludeCaseId) < SMARTOPS_MAX_ACTIVE_TASKS;
}


// =============================================================================
// SECTION: Technician Remaining Capacity
// =============================================================================
function technician_remaining_capacity(string $technicianId): int {
    return max(0, SMARTOPS_MAX_ACTIVE_TASKS - technician_active_task_count($technicianId));
}


// =============================================================================
// SECTION: Fetch Technicians With Workload
// =============================================================================
function fetch_technicians_with_workload(string $role, string $department = ''): array {
    $sql = "SELECT t.*,
                   COALESCE(open_tasks.active_tasks,0) AS active_tasks,
                   GREATEST(0, ? - COALESCE(open_tasks.active_tasks,0)) AS remaining_capacity,
                   ROUND((COALESCE(open_tasks.active_tasks,0) / ?) * 100) AS workload_percent
            FROM technicians t
            LEFT JOIN (" . technician_active_task_sql() . ") open_tasks
              ON open_tasks.technician_id=t.technician_id
            WHERE t.is_active=1 AND t.role=?";
    $params = [SMARTOPS_MAX_ACTIVE_TASKS, SMARTOPS_MAX_ACTIVE_TASKS, $role];

    if ($department !== '' && $role !== 'Technical Specialist') {
        $sql .= " AND COALESCE(NULLIF(t.department,''),t.category)=?";
        $params[] = normalize_technician_department($department);
    }

    $sql .= " ORDER BY t.department, COALESCE(open_tasks.active_tasks,0), t.name, t.technician_id";
    return fetch_all($sql, $params);
}


// =============================================================================
// SECTION: Fetch Assignable Technicians
// =============================================================================
function fetch_assignable_technicians(string $role, string $department = ''): array {
    $sql = "SELECT t.*,
                   COALESCE(open_tasks.active_tasks,0) AS active_tasks,
                   GREATEST(0, ? - COALESCE(open_tasks.active_tasks,0)) AS remaining_capacity,
                   ROUND((COALESCE(open_tasks.active_tasks,0) / ?) * 100) AS workload_percent
            FROM technicians t
            LEFT JOIN (" . technician_active_task_sql() . ") open_tasks
              ON open_tasks.technician_id=t.technician_id
            WHERE t.role=?
              AND " . technician_assignable_status_sql() . "
              AND COALESCE(open_tasks.active_tasks,0) < ?";
    $params = [
        SMARTOPS_MAX_ACTIVE_TASKS,
        SMARTOPS_MAX_ACTIVE_TASKS,
        $role,
        SMARTOPS_MAX_ACTIVE_TASKS,
    ];

    if ($department !== '' && $role !== 'Technical Specialist') {
        $sql .= " AND COALESCE(NULLIF(t.department,''),t.category)=?";
        $params[] = normalize_technician_department($department);
    }

    $sql .= " ORDER BY COALESCE(open_tasks.active_tasks,0), t.department, t.name, t.technician_id";
    return fetch_all($sql, $params);
}


// =============================================================================
// SECTION: Fetch Assignable Senior Technicians
// =============================================================================
function fetch_assignable_senior_technicians(string $sourceRole = '', string $department = ''): array {
    ensure_technician_level_column();
    $sql = "SELECT t.*, COALESCE(open_tasks.active_tasks,0) AS active_tasks,
                   GREATEST(0, ? - COALESCE(open_tasks.active_tasks,0)) AS remaining_capacity,
                   ROUND((COALESCE(open_tasks.active_tasks,0) / ?) * 100) AS workload_percent
            FROM technicians t
            LEFT JOIN (" . technician_active_task_sql() . ") open_tasks ON open_tasks.technician_id=t.technician_id
            WHERE t.technician_level='Senior' AND " . technician_assignable_status_sql() . " AND COALESCE(open_tasks.active_tasks,0)=0";
    $params = [SMARTOPS_MAX_ACTIVE_TASKS, SMARTOPS_MAX_ACTIVE_TASKS];
    if ($sourceRole === 'Technical Specialist') {
        $sql .= " AND t.role='Technical Specialist'";
    } elseif ($sourceRole === 'Technician') {
        $sql .= " AND t.role='Technician' AND COALESCE(NULLIF(t.department,''),t.category)=?";
        $params[] = normalize_technician_department($department);
    } else {
        $sql .= " AND t.role IN ('Technician','Technical Specialist')";
    }
    $sql .= " ORDER BY t.role='Technical Specialist' DESC, t.department, t.name, t.technician_id";
    return fetch_all($sql, $params);
}


// =============================================================================
// SECTION: Refresh Technician Summary
// =============================================================================
function refresh_technician_summary(string $technicianId): void {
    if ($technicianId === '') return;

    $active = technician_active_task_count($technicianId);
    if (db_table_exists('task_assignment_history')) {
        // One history row represents one actual assignment, including released work.
        $total = scalar("SELECT COUNT(*) FROM task_assignment_history WHERE technician_id=?", [$technicianId]);
        $completed = scalar("SELECT COUNT(*) FROM task_assignment_history WHERE technician_id=? AND end_status='Repair Completed'", [$technicianId]);
    } else {
        $completed = scalar("SELECT COUNT(*) FROM technician_tasks WHERE technician_id=? AND task_status='Completed'", [$technicianId]);
        $total = $active + $completed;
    }
    $overdue = scalar(
        "SELECT COUNT(*) FROM technician_tasks
         WHERE technician_id=?
           AND task_status<>'Completed'
           AND sla_status IN ('Response Breach','Repair Breach','Breach','SLA Violation')",
        [$technicianId]
    );
    $current = fetch_one('SELECT status, is_active FROM technicians WHERE technician_id=?', [$technicianId]);
    if (!$current || (int)($current['is_active'] ?? 0) !== 1) return;
    $onLeave = str_contains(strtolower((string)($current['status'] ?? '')), 'leave');
    $status = $onLeave ? 'On Leave' : ($active > 0 ? 'Busy' : 'Available');
    $workload = min(100, (int)round(($active / SMARTOPS_MAX_ACTIVE_TASKS) * 100));
    $warning = $onLeave
        ? 'Temporarily unavailable for assignment'
        : ($active > 0 ? 'Currently handling one active task' : '');

    execute_sql(
        "UPDATE technicians
         SET total_tasks=?, active_tasks=?, completed_tasks=?, overdue_tasks=?,
             workload_percent=?, status=?, warning_reason=?
         WHERE technician_id=?",
        [$total, $active, $completed, $overdue, $workload, $status, $warning, $technicianId]
    );
}


// =============================================================================
// SECTION: Available Technician Count
// =============================================================================
function available_technician_count(string $department = ''): int {
    $sql = "SELECT COUNT(*)
            FROM technicians t
            LEFT JOIN (" . technician_active_task_sql() . ") open_tasks
              ON open_tasks.technician_id=t.technician_id
            WHERE " . technician_assignable_status_sql() . "
              AND COALESCE(open_tasks.active_tasks,0) < ?";
    $params = [SMARTOPS_MAX_ACTIVE_TASKS];

    if (trim($department) !== '') {
        $normalizedDepartment = normalize_technician_department($department);
        if ($normalizedDepartment === 'Technical Specialist') {
            $sql .= " AND t.role='Technical Specialist'";
        } else {
            $aliases = match ($normalizedDepartment) {
                'Fire Protection' => ['Fire Protection', 'Fire'],
                'Conveying' => ['Conveying', 'Elevator'],
                default => [$normalizedDepartment],
            };
            $placeholders = implode(',', array_fill(0, count($aliases), '?'));
            $sql .= " AND t.role='Technician'
                      AND COALESCE(NULLIF(t.department,''), t.category) IN ({$placeholders})";
            array_push($params, ...$aliases);
        }
    }

    return scalar($sql, $params);
}

/**
 * HITL review-reason helpers.
 * The primary queues use this precedence:
 * Out of Knowledge Base -> Critical -> High -> Low Confidence.
 * Low Confidence is a manager-facing review reason and never hides
 * Category, Severity or Priority. Only OOKB has restricted classification.
 */

// =============================================================================
// SECTION: HITL Is Critical Case
// =============================================================================
function hitl_is_critical_case(array $row): bool {
    $severity = strtolower(trim((string)($row['severity'] ?? '')));
    $priority = strtolower(trim((string)($row['priority'] ?? '')));
    return str_contains($severity, 'critical') || str_starts_with($priority, 'p1') || str_contains($priority, 'immediate');
}


// =============================================================================
// SECTION: HITL Is High Severity Case
// =============================================================================
function hitl_is_high_severity_case(array $row): bool {
    $severity = strtolower(trim((string)($row['severity'] ?? '')));
    $priority = strtolower(trim((string)($row['priority'] ?? '')));
    return str_contains($severity, 'high') || str_starts_with($priority, 'p2') || str_contains($priority, 'urgent');
}


// =============================================================================
// SECTION: HITL Is Low Confidence Case
// =============================================================================
function hitl_is_low_confidence_case(array $row): bool {
    $storedReason = strtolower(trim((string)($row['hitl_reason'] ?? '')));
    if (str_contains($storedReason, 'low confidence')) return true;

    $raw = trim((string)($row['ai_confidence'] ?? ''));
    if ($raw === '') return false;

    $value = strtolower(str_replace('%', '', $raw));
    if (str_contains($value, 'low')) return true;
    if (!is_numeric($value)) return false;

    $number = (float)$value;
    if ($number > 1) $number /= 100;
    return $number < 0.75;
}


// =============================================================================
// SECTION: HITL Is Out Of Knowledge Case
// =============================================================================
function hitl_is_out_of_knowledge_case(array $row): bool {
    return str_contains(strtolower(trim((string)($row['hitl_reason'] ?? ''))), 'out of knowledge');
}


// =============================================================================
// SECTION: HITL Is Restricted Detail Case
// =============================================================================
function hitl_is_restricted_detail_case(array $row): bool {
    return hitl_is_out_of_knowledge_case($row);
}


// =============================================================================
// SECTION: HITL Primary Escalation Trigger
// =============================================================================
function hitl_primary_escalation_trigger(array $row): string {
    $storedReason = strtolower(trim((string)($row['hitl_reason'] ?? '')));

    // One case belongs to one primary HITL queue. Safety and urgency always
    // take precedence over confidence, while OUT_OF_KB remains its own queue.
    if (str_contains($storedReason, 'out of knowledge')) return 'Out of Knowledge Base';
    if (hitl_is_critical_case($row) || str_contains($storedReason, 'critical')) return 'Critical';
    if (hitl_is_high_severity_case($row)
        || $storedReason === 'high'
        || str_contains($storedReason, 'high severity')
        || str_contains($storedReason, 'urgent')) {
        return 'High';
    }
    if (str_contains($storedReason, 'low confidence') || hitl_is_low_confidence_case($row)) {
        return 'Low Confidence';
    }

    return 'Low Confidence';
}


// =============================================================================
// SECTION: Workforce Asset Department
// =============================================================================
function workforce_asset_department(string $asset): string {
    $clean = strtolower(clean_asset_name($asset));
    if (str_contains($clean, 'hvac')) return 'HVAC';
    if (str_contains($clean, 'plumb')) return 'Plumbing';
    if (str_contains($clean, 'electric')) return 'Electrical';
    if (str_contains($clean, 'fire')) return 'Fire Protection';
    if (str_contains($clean, 'elevator') || str_contains($clean, 'convey')) return 'Conveying';
    return 'Other';
}


// =============================================================================
// SECTION: Workforce SLA Department
// =============================================================================
function workforce_sla_department(array $row): string {
    $reason = strtolower((string)($row['joined_hitl_reason'] ?? $row['hitl_reason'] ?? ''));
    if (str_contains($reason, 'out of knowledge')) return 'Out of Knowledge Base';
    return workforce_asset_department((string)($row['hotel_asset'] ?? ''));
}


/**
 * Return the current rolling seven-day window in Kuala Lumpur time.
 * The window includes today and the six preceding calendar days.
 * The end value is exclusive so it can be used safely in SQL ranges.
 *
 * @return array{start:string,end_exclusive:string,today:string,label:string}
 */

// =============================================================================
// SECTION: Rolling Seven Day Window
// =============================================================================
function rolling_seven_day_window(): array {
    $timezone = new DateTimeZone('Asia/Kuala_Lumpur');
    $today = new DateTimeImmutable('today', $timezone);
    $start = $today->modify('-6 days');
    $endExclusive = $today->modify('+1 day');

    return [
        'start' => $start->format('Y-m-d 00:00:00'),
        'end_exclusive' => $endExclusive->format('Y-m-d 00:00:00'),
        'today' => $today->format('Y-m-d'),
        'label' => $start->format('d M Y') . ' – ' . $today->format('d M Y'),
    ];
}
