<?php
/**
 * SmartOps Source: admin_portal/technicians/action.php
 * Purpose: Process Technician Management create, update, and deactivate actions.
 *
 * Developer Guide: section titles below separate dependencies, access control,
 * business/data logic, reusable functions, and visible interface components.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../admin_auth.php';

// =============================================================================
// SECTION: Authentication and Access Control
// =============================================================================
require_admin();
ensure_technician_level_column();


// =============================================================================
// SECTION: Form Processing and Validation
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to(app_url('admin_portal/technicians/index.php'));
}


// =============================================================================
// SECTION: Technician Management Redirect
// =============================================================================
function technician_management_redirect(string $type, string $message, string $records = 'active'): void {
    $records = in_array($records, ['deactivated', 'deleted'], true) ? 'deactivated' : 'active';
    redirect_to(app_url('admin_portal/technicians/index.php?' . http_build_query([
        'records' => $records,
        $type => $message,
    ])));
}


// =============================================================================
// SECTION: Release Active Technician Tasks
// =============================================================================
function release_active_technician_tasks(string $technicianId, string $reason): void {

    // =============================================================================
    // SECTION: Database Queries and Data Preparation
    // =============================================================================
    $sourceTechnician = fetch_one('SELECT * FROM technicians WHERE technician_id=?', [$technicianId]);
    $sourceRole = (string)($sourceTechnician['role'] ?? 'Technician');
    $sourceDepartment = normalize_technician_department((string)($sourceTechnician['department'] ?? $sourceTechnician['category'] ?? ''));
    $note = $reason . ' The active work order was released for reassignment.';
    $cases = fetch_all(
        "SELECT DISTINCT case_id FROM technician_tasks
         WHERE technician_id=?
           AND task_status IN ('Assigned','Waiting Technician Acceptance','Accepted','In Progress','Repair In Progress','Started')",
        [$technicianId]
    );

    foreach ($cases as $row) {
        $caseId = (string)$row['case_id'];
        close_assignment_history($caseId, $technicianId, 'Released - Technician Unavailable', $note);
        execute_sql(
            "UPDATE workforce_cases
             SET previous_technician_id=?, assigned_technician_id=NULL, technician_id=NULL,
                 work_stage='Pending Assignment', task_status='Pending Assignment',
                 released_at=NOW(), transfer_reason='Technician Unavailable', manager_note=?
             WHERE case_id=? AND work_stage<>'Completed'",
            [$technicianId, $note, $caseId]
        );
        execute_sql(
            "UPDATE technician_tasks
             SET previous_technician_id=?, technician_id=NULL, assigned_technician_id=NULL,
                 task_status='Pending Assignment', released_at=NOW(), technician_note=?
             WHERE case_id=? AND task_status<>'Completed'",
            [$technicianId, $note, $caseId]
        );
        if (db_table_exists('smart_maintenance_tickets')) {
            execute_sql(
                "UPDATE smart_maintenance_tickets
                 SET technician_assigned=NULL, technician_assigned_timestamp=NULL,
                     assigned_to=NULL, ticket_status='Pending Assignment'
                 WHERE case_id=? AND ticket_status<>'Completed'",
                [$caseId]
            );
        }

        // Keep the one-task workflow moving. Prefer another available technician
        // from the same workflow group; never assign the released case back to the
        // technician who was placed on leave or removed.
        $replacement = null;
        foreach (fetch_assignable_technicians($sourceRole, $sourceDepartment) as $candidate) {
            if ((string)($candidate['technician_id'] ?? '') === $technicianId) continue;
            $replacement = $candidate;
            break;
        }
        if ($replacement) {
            assign_case_to_technician(
                $caseId,
                (string)$replacement['technician_id'],
                'Technician Unavailable - Reassigned',
                $note
            );
        }
    }
    refresh_technician_summary($technicianId);
}


$token = (string)($_POST['csrf_token'] ?? '');
if (!verify_technician_management_csrf($token)) {
    technician_management_redirect('error', 'The form expired. Please try again.');
}

$action = strtolower(trim((string)($_POST['action'] ?? '')));
$departments = ['HVAC', 'Plumbing', 'Electrical', 'Fire Protection', 'Conveying', 'All Departments'];
$roles = ['Technician', 'Technical Specialist'];
$levels = ['Junior', 'Senior'];

$pdo = smartops_pdo();

try {
    // -------------------------------------------------------------------------
    // SECTION: Add or Update Technician
    // -------------------------------------------------------------------------
    if ($action === 'add' || $action === 'update') {
        $technicianId = strtoupper(trim((string)($_POST['technician_id'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $department = normalize_technician_department((string)($_POST['department'] ?? ''));
        $role = trim((string)($_POST['role'] ?? ''));
        $technicianLevel = ucfirst(strtolower(trim((string)($_POST['technician_level'] ?? 'Junior'))));
        if ($role === 'Technical Specialist') {
            $department = 'All Departments';
        }
        $password = trim((string)($_POST['password'] ?? ''));

        // ---------------------------------------------------------------------
        // SECTION: Technician Input Validation
        // ---------------------------------------------------------------------
        if ($action === 'add' && $technicianId === '') {
            $technicianId = next_technician_id();
        }

        if (!preg_match('/^TECH-\d{3,}$/', $technicianId)) {
            technician_management_redirect('error', 'Technician ID must use the TECH-001 format.');
        }
        $hasInvalidRequiredData = $name === ''
            || !in_array($department, $departments, true)
            || !in_array($role, $roles, true)
            || !in_array($technicianLevel, $levels, true)
            || ($role !== 'Technical Specialist' && $department === 'All Departments');

        if ($hasInvalidRequiredData) {
            technician_management_redirect('error', 'Complete all required technician information.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            technician_management_redirect('error', 'Enter a valid email address.');
        }

        // ---------------------------------------------------------------------
        // SECTION: Create Technician Record
        // ---------------------------------------------------------------------
        if ($action === 'add') {
            if ($password === '') $password = strtolower(str_replace('-', '', $technicianId));
            if (strlen($password) < 6) {
                technician_management_redirect('error', 'The technician password must contain at least 6 characters.');
            }
            if (fetch_one('SELECT technician_id FROM technicians WHERE technician_id=?', [$technicianId])) {
                technician_management_redirect('error', 'That Technician ID already exists.');
            }

            $pdo->beginTransaction();
            execute_sql(
                "INSERT INTO technicians
                 (technician_id,password,name,phone,email,department,category,role,technician_level,status,
                  total_tasks,active_tasks,completed_tasks,overdue_tasks,workload_percent,warning_reason,is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,'0','0','0','0','0','',1)",
                [$technicianId, password_hash($password, PASSWORD_DEFAULT), $name, $phone, $email, $department, $department, $role, $technicianLevel, 'Available']
            );
            refresh_technician_summary($technicianId);
            $pdo->commit();
            technician_management_redirect('success', $technicianId . ' was added and is now linked to Workforce and the Technician Website.');
        }

        // ---------------------------------------------------------------------
        // SECTION: Update Technician Record
        // ---------------------------------------------------------------------
        $current = fetch_one('SELECT * FROM technicians WHERE technician_id=? AND is_active=1', [$technicianId]);
        if (!$current) technician_management_redirect('error', 'The selected technician could not be found.');

        $activeCount = technician_active_task_count($technicianId);
        $roleChanged = (string)$current['role'] !== $role;
        $departmentChanged = normalize_technician_department((string)$current['department']) !== $department;
        if ($activeCount > 0 && ($roleChanged || $departmentChanged)) {
            technician_management_redirect('error', 'Department or role cannot be changed while the technician has active tasks.');
        }

        $setPassword = $password !== '' ? ', password=?' : '';
        $params = [$name, $phone, $email, $department, $department, $role, $technicianLevel];
        if ($password !== '') {
            if (strlen($password) < 6) technician_management_redirect('error', 'The technician password must contain at least 6 characters.');
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $params[] = $technicianId;
        $pdo->beginTransaction();
        execute_sql(
            "UPDATE technicians
             SET name=?, phone=?, email=?, department=?, category=?, role=?, technician_level=? {$setPassword}
             WHERE technician_id=? AND is_active=1",
            $params
        );

        refresh_technician_summary($technicianId);
        $pdo->commit();
        technician_management_redirect('success', $technicianId . ' was updated successfully.');
    }

    // -------------------------------------------------------------------------
    // SECTION: Deactivate Technician Record
    // -------------------------------------------------------------------------
    if ($action === 'deactivate' || $action === 'delete') {
        $technicianId = strtoupper(trim((string)($_POST['technician_id'] ?? '')));
        $current = fetch_one('SELECT * FROM technicians WHERE technician_id=? AND is_active=1', [$technicianId]);
        if (!$current) technician_management_redirect('error', 'The selected technician could not be found.');

        $pdo->beginTransaction();
        release_active_technician_tasks($technicianId, $technicianId . ' was deactivated by an administrator.');
        execute_sql(
            "UPDATE technicians
             SET is_active=0, status='Resigned', deleted_at=NOW(), warning_reason='Deactivated through Technician Management'
             WHERE technician_id=?",
            [$technicianId]
        );
        $pdo->commit();
        if (($_SESSION['technician_id'] ?? '') === $technicianId) unset($_SESSION['technician_id']);
        technician_management_redirect('success', $technicianId . ' was deactivated. Account access is disabled and historical work records were preserved.', 'deactivated');
    }

    technician_management_redirect('error', 'Unsupported technician management action.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    technician_management_redirect('error', 'The technician change could not be completed. Check the database and try again.');
}
