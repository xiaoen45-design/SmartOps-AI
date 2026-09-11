<?php
/**
 * SmartOps Source: admin_portal/technicians/index.php
 * Purpose: Display the Technician Management directory and CRUD interface.
 *
 * Developer Guide: KPI cards, filters, table records, and CRUD modals are
 * separated into named sections for short and focused screenshots.
 */

// =============================================================================
// SECTION: Dependencies
// =============================================================================
require_once __DIR__ . '/../../includes/admin_layout.php';
require_once __DIR__ . '/../admin_auth.php';

// =============================================================================
// SECTION: Authentication and Access Control
// =============================================================================
require_admin();

// =============================================================================
// SECTION: Page Header
// =============================================================================
admin_ui_header(
    'Technician Management',
    'technicians',
    'Manage technician accounts, departments, roles, access, and contact information from one admin workspace.'
);

// =============================================================================
// SECTION: Database Readiness Check
// =============================================================================
$dbMessage = database_ready_message();

if ($dbMessage !== '') {
    ?>
    <section class="card">
        <h2>Database is not ready</h2>
        <p><?= e($dbMessage) ?></p>
    </section>
    <?php
    admin_ui_footer();
    exit;
}

// =============================================================================
// SECTION: Record View Filter
// =============================================================================
$recordView = strtolower(trim((string) ($_GET['records'] ?? 'active')));

// Keep old bookmarks working after the label changed from deleted to deactivated.
if ($recordView === 'deleted') {
    $recordView = 'deactivated';
}

if (!in_array($recordView, ['active', 'deactivated'], true)) {
    $recordView = 'active';
}

$showDeactivated = $recordView === 'deactivated';
$where = $showDeactivated ? 't.is_active = 0' : 't.is_active = 1';

// =============================================================================
// SECTION: Technician Directory Query
// =============================================================================
// This page manages technician master data. Live workload and availability are
// monitored separately in Workforce and Task Monitoring.
$technicians = fetch_all(
    "SELECT
        t.*,
        COALESCE(open_tasks.active_tasks, 0) AS live_active_tasks
     FROM technicians t
     LEFT JOIN (" . technician_active_task_sql() . ") open_tasks
        ON open_tasks.technician_id = t.technician_id
     WHERE {$where}
     ORDER BY
        t.is_active DESC,
        FIELD(
            t.department,
            'HVAC',
            'Plumbing',
            'Electrical',
            'Fire Protection',
            'Conveying',
            'All Departments'
        ),
        FIELD(t.role, 'Technician', 'Technical Specialist'),
        t.technician_id"
);

// =============================================================================
// SECTION: Technician KPI Data
// =============================================================================
$totalActive = scalar("SELECT COUNT(*) FROM technicians WHERE is_active = 1");
$regularTechnicians = scalar(
    "SELECT COUNT(*)
     FROM technicians
     WHERE is_active = 1
       AND role = 'Technician'"
);
$specialists = scalar(
    "SELECT COUNT(*)
     FROM technicians
     WHERE is_active = 1
       AND role = 'Technical Specialist'"
);
$activeDepartments = scalar(
    "SELECT COUNT(DISTINCT department)
     FROM technicians
     WHERE is_active = 1
       AND role = 'Technician'
       AND department IN ('HVAC', 'Plumbing', 'Electrical', 'Fire Protection', 'Conveying')"
);
$deactivated = scalar("SELECT COUNT(*) FROM technicians WHERE is_active = 0");
$nextId = next_technician_id();
$csrf = technician_management_csrf_token();
$success = trim((string) ($_GET['success'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));

// =============================================================================
// SECTION: Technician Modal Data Preparation
// =============================================================================
$payload = [];

foreach ($technicians as $technician) {
    $isActive = (int) $technician['is_active'] === 1;

    $payload[$technician['technician_id']] = [
        'technician_id' => $technician['technician_id'],
        'name' => $technician['name'],
        'phone' => $technician['phone'],
        'email' => $technician['email'],
        'department' => technician_department_label($technician),
        'role' => $technician['role'],
        'technician_type' => (string) $technician['role'] === 'Technical Specialist'
            ? 'HITL Specialist'
            : 'AI Automation Technician',
        'technician_level' => technician_level_label($technician),
        'account_status' => $isActive ? 'Active' : 'Deactivated',
        'is_active' => (int) $technician['is_active'],
        // Used only to protect assignment integrity during edit/deactivation.
        'active_tasks' => (int) $technician['live_active_tasks'],
        'deactivated_at' => $technician['deleted_at'] ?? null,
    ];
}
?>

<!-- ==========================================================================
     SECTION: Status Messages
     ========================================================================== -->
<?php if ($success !== ''): ?>
    <div class="management-message success-message" role="status">
        <?= e($success) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="management-message error-message" role="alert">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- ==========================================================================
     SECTION: KPI Cards
     ========================================================================== -->
<section class="kpis four technician-management-kpis master-data-kpis">
    <div class="kpi">
        <h3>Active Technician Accounts</h3>
        <h2><?= e(number_format($totalActive)) ?></h2>
        <p>Technicians with active system access</p>
    </div>

    <div class="kpi">
        <h3>AI Automation Technicians</h3>
        <h2><?= e(number_format($regularTechnicians)) ?></h2>
        <p>Department-based maintenance technicians</p>
    </div>

    <div class="kpi">
        <h3>HITL Specialists</h3>
        <h2><?= e(number_format($specialists)) ?></h2>
        <p>Specialists handling escalated cases</p>
    </div>

    <div class="kpi">
        <h3>Maintenance Departments</h3>
        <h2><?= e(number_format($activeDepartments)) ?></h2>
        <p>Departments represented by active technicians</p>
    </div>
</section>

<!-- ==========================================================================
     SECTION: Technician Directory Card
     ========================================================================== -->
<section class="card management-table-card">
    <!-- SECTION: Directory Header -->
    <div class="management-header simplified-management-header">
        <div>
            <h2>Technician Directory</h2>
            <p>
                Manage technician master records and account access. Live workload
                and availability remain in Workforce &amp; Task Monitoring.
            </p>
        </div>

        <div class="management-header-actions">
            <button class="primary-btn add-technician-btn" type="button" data-open-add>
                + Add Technician
            </button>
        </div>
    </div>

    <!-- SECTION: Active and Deactivated Tabs -->
    <div class="directory-record-tabs" aria-label="Technician account view">
        <a class="<?= !$showDeactivated ? 'active' : '' ?>" href="?records=active">
            Active Technicians <span><?= e($totalActive) ?></span>
        </a>
        <a class="<?= $showDeactivated ? 'active' : '' ?>" href="?records=deactivated">
            Deactivated <span><?= e($deactivated) ?></span>
        </a>
    </div>

    <!-- SECTION: Technician Filters -->
    <div class="management-filters simplified-filters master-data-filters" aria-label="Technician filters">
        <label class="search-field">
            <span>Search Technicians</span>
            <input
                type="search"
                id="technicianSearch"
                placeholder="Search ID, name, email, or phone"
            >
        </label>

        <label>
            <span>Department</span>
            <select id="departmentFilter">
                <option value="">All Departments</option>
                <option value="All Departments">All Departments (HITL)</option>
                <option>HVAC</option>
                <option>Plumbing</option>
                <option>Electrical</option>
                <option>Fire Protection</option>
                <option>Conveying</option>
            </select>
        </label>

        <label>
            <span>Technician Type</span>
            <select id="roleFilter">
                <option value="">All Types</option>
                <option value="Technician">AI Automation Technician</option>
                <option value="Technical Specialist">HITL Specialist</option>
            </select>
        </label>

        <label>
            <span>Level</span>
            <select id="levelFilter">
                <option value="">All Levels</option>
                <option>Junior</option>
                <option>Senior</option>
            </select>
        </label>

        <button type="button" class="filter-reset-btn" id="resetTechnicianFilters">
            Reset
        </button>
    </div>

    <!-- SECTION: Technician Records Table -->
    <div class="table-wrap management-table-wrap master-data-table-wrap">
        <table id="technicianManagementTable" class="master-data-table">
            <thead>
                <tr>
                    <th>Technician</th>
                    <th>Department</th>
                    <th>Technician Type</th>
                    <th>Level</th>
                    <th>Contact</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$technicians): ?>
                    <tr>
                        <td colspan="6" class="empty-table-message">
                            <?= $showDeactivated
                                ? 'No deactivated technician records were found.'
                                : 'No active technician records were found.' ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($technicians as $technician): ?>
                    <?php
                    $isActive = (int) $technician['is_active'] === 1;
                    $roleLabel = (string) $technician['role'] === 'Technical Specialist'
                        ? 'HITL Specialist'
                        : 'AI Automation Technician';
                    $roleClass = (string) $technician['role'] === 'Technical Specialist'
                        ? 'hitl-role'
                        : 'ai-role';
                    $searchText = strtolower(implode(' ', [
                        $technician['technician_id'],
                        $technician['name'],
                        $technician['email'],
                        $technician['phone'],
                        technician_department_label($technician),
                        $roleLabel,
                        technician_level_label($technician),
                    ]));
                    ?>
                    <tr
                        class="<?= !$isActive ? 'inactive-technician-row' : '' ?>"
                        data-technician-id="<?= e($technician['technician_id']) ?>"
                        data-search="<?= e($searchText) ?>"
                        data-department="<?= e(technician_department_label($technician)) ?>"
                        data-role="<?= e($technician['role']) ?>"
                        data-level="<?= e(technician_level_label($technician)) ?>"
                    >
                        <td>
                            <button
                                type="button"
                                class="technician-identity-link"
                                data-view-technician="<?= e($technician['technician_id']) ?>"
                                aria-label="View <?= e($technician['technician_id']) ?> details"
                            >
                                <strong><?= e($technician['technician_id']) ?></strong>
                                <small><?= e($technician['name'] ?: '-') ?></small>
                            </button>
                        </td>
                        <td><?= e(technician_department_label($technician)) ?></td>
                        <td>
                            <span class="role-badge <?= e($roleClass) ?>">
                                <?= e($roleLabel) ?>
                            </span>
                        </td>
                        <td>
                            <span class="level-badge level-<?= e(strtolower(technician_level_label($technician))) ?>">
                                <?= e(technician_level_label($technician)) ?>
                            </span>
                        </td>
                        <td>
                            <strong class="contact-main"><?= e($technician['email'] ?: '-') ?></strong>
                            <small><?= e($technician['phone'] ?: '-') ?></small>
                        </td>
                        <td>
                            <div class="row-actions">
                                <?php if ($isActive): ?>
                                    <button
                                        type="button"
                                        class="table-action edit-action"
                                        data-edit-technician="<?= e($technician['technician_id']) ?>"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="table-action deactivate-action"
                                        data-deactivate-technician="<?= e($technician['technician_id']) ?>"
                                    >
                                        Deactivate
                                    </button>
                                <?php else: ?>
                                    <span class="deleted-label">
                                        Deactivated<?= e(
                                            $technician['deleted_at']
                                                ? ' ' . date('d M Y', strtotime((string) $technician['deleted_at']))
                                                : ''
                                        ) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="table-result-count" id="technicianResultCount">
        <?= e(number_format(count($technicians))) ?> technician record(s)
    </div>
</section>

<!-- ==========================================================================
     SECTION: View Technician Modal
     ========================================================================== -->
<div class="management-modal" id="technicianSelectModal" aria-hidden="true">
    <div
        class="management-modal-dialog select-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="technicianSelectTitle"
    >
        <button class="management-modal-close" type="button" aria-label="Close">×</button>

        <div class="modal-heading">
            <span>Technician Profile</span>
            <h2 id="technicianSelectTitle">Technician Details</h2>
            <p>Administrative profile and account information.</p>
        </div>

        <div class="selected-technician-grid" id="selectedTechnicianGrid"></div>

        <div class="modal-actions">
            <button type="button" class="secondary-btn" data-close-modal>Close</button>
        </div>
    </div>
</div>

<!-- ==========================================================================
     SECTION: Add and Edit Technician Modal
     ========================================================================== -->
<div class="management-modal" id="technicianFormModal" aria-hidden="true">
    <div
        class="management-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="technicianFormTitle"
    >
        <button class="management-modal-close" type="button" aria-label="Close">×</button>

        <div class="modal-heading">
            <span>Technician Management</span>
            <h2 id="technicianFormTitle">Add Technician</h2>
            <p id="technicianFormSubtitle">Create a technician account and administrative profile.</p>
        </div>

        <!-- SECTION: Technician CRUD Form -->
        <form method="post" action="action.php" id="technicianForm" class="management-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" id="technicianFormAction" value="add">

            <div class="form-grid">
                <label>
                    <span>Technician ID</span>
                    <input
                        name="technician_id"
                        id="formTechnicianId"
                        value="<?= e($nextId) ?>"
                        required
                        pattern="TECH-[0-9]{3,}"
                    >
                    <small>Use the technician format, for example TECH-026.</small>
                </label>

                <label>
                    <span>Full Name</span>
                    <input name="name" id="formName" required>
                </label>

                <label>
                    <span>Department</span>
                    <select name="department" id="formDepartment" required>
                        <option value="">Select Department</option>
                        <option value="All Departments">All Departments (HITL only)</option>
                        <option>HVAC</option>
                        <option>Plumbing</option>
                        <option>Electrical</option>
                        <option>Fire Protection</option>
                        <option>Conveying</option>
                    </select>
                </label>

                <label>
                    <span>Technician Type</span>
                    <select name="role" id="formRole" required>
                        <option value="Technician">AI Automation Technician</option>
                        <option value="Technical Specialist">HITL Specialist</option>
                    </select>
                </label>

                <label>
                    <span>Technician Level</span>
                    <select name="technician_level" id="formTechnicianLevel" required>
                        <option value="Junior">Junior</option>
                        <option value="Senior">Senior</option>
                    </select>
                </label>

                <label>
                    <span>Phone</span>
                    <input name="phone" id="formPhone" inputmode="tel">
                </label>

                <label>
                    <span>Email</span>
                    <input name="email" id="formEmail" type="email">
                </label>

                <label>
                    <span>Password</span>
                    <input
                        name="password"
                        id="formPassword"
                        type="password"
                        minlength="6"
                        placeholder="Leave blank during update to keep current password"
                    >
                    <small id="passwordHelp">
                        A default password is created from the Technician ID when blank.
                    </small>
                </label>
            </div>

            <div class="modal-actions">
                <button type="button" class="secondary-btn" data-close-modal>Cancel</button>
                <button type="submit" class="primary-btn" id="technicianFormSubmit">
                    Add Technician
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================================================
     SECTION: Deactivate Technician Modal
     ========================================================================== -->
<div class="management-modal" id="technicianDeactivateModal" aria-hidden="true">
    <div
        class="management-modal-dialog delete-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="technicianDeactivateTitle"
    >
        <button class="management-modal-close" type="button" aria-label="Close">×</button>
        <div class="deactivate-icon">!</div>

        <div class="modal-heading centered">
            <span>Deactivate Account</span>
            <h2 id="technicianDeactivateTitle">Deactivate this technician?</h2>
            <p>
                This disables future login and new assignments while preserving
                the technician record and completed work history.
            </p>
        </div>

        <div class="deactivate-summary" id="technicianDeactivateSummary"></div>

        <!-- SECTION: Deactivate Technician Form -->
        <form method="post" action="action.php">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="deactivate">
            <input type="hidden" name="technician_id" id="deactivateTechnicianId">

            <div class="modal-actions">
                <button type="button" class="secondary-btn" data-close-modal>Cancel</button>
                <button type="submit" class="danger-btn">Deactivate Technician</button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================================================
     SECTION: Technician Management Page Data
     JavaScript behaviour is kept in assets/js/admin/technicians.js.
     ========================================================================== -->
<script>
window.technicianManagementData = <?= json_encode(
    ['records' => $payload, 'nextId' => $nextId],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?>;
</script>

<?php admin_ui_footer(); ?>
