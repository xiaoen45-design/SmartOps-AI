# SmartOps AI Developer Code Index

Use this file to find short, clearly titled code blocks for the Developer Guide. The source files contain `SECTION:` comments so you do not need to screenshot or paste an entire file.

## Recommended Developer Guide Evidence

| Topic | Main file | Search for this section |
|---|---|---|
| Database connection | `config/db.php` | `SmartOps PDO Connection` |
| Shared database helpers | `includes/helpers.php` | `Fetch All`, `Fetch One`, `Execute Sql` |
| Admin login | `admin_portal/login.php` | `Form Processing and Validation`, `Database Queries and Data Preparation` |
| Admin sidebar / shared layout | `includes/admin_layout.php` | `Admin UI Header`, `Admin Nav Link`, `Admin Dashboard Kpis` |
| Executive KPI cards | `admin_portal/overview/dashboard.php` | `KPI Cards` |
| HITL approval and assignment | `admin_portal/hitl/action.php` | `HITL Approval Transaction`, `Create or Update Workforce Work Order`, `Create or Update Technician Task`, `Assign Approved Case to Technical Specialist` |
| HITL review modal | `admin_portal/hitl/review_modal.php` | `HITL Review Modal`, `Technician Assignment Form` |
| Technician CRUD | `admin_portal/technicians/index.php`, `action.php` | `Technician CRUD Form`, `Technician Input Validation`, `Create Technician Record`, `Update Technician Record`, `Deactivate Technician Record` |
| Technician login | `technician_website/login.php` | `Form Processing and Validation`, `Database Queries and Data Preparation` |
| Technician assigned tasks | `technician_website/home.php` | `Database Queries and Data Preparation`, `Tech Action Button`, `Page Layout and Rendering` |
| Technician Accept / Start / Complete / Support | `technician_website/task_action.php` | `Accept Task`, `Start Repair`, `Complete Repair`, `Support Request Validation`, `Save Support Request Details` |
| Technician phone frame / scrolling | `technician_website/assets/css/technician.css` | `Phone Device Frame`, `In-Phone Scrolling and Login Frame` |
| Technician mobile interactions | `technician_website/assets/js/technician.js` | `Init Mobile Navigation`, `Init Task Action Modal` |
| Workforce support manager flow | `admin_portal/workforce/action_support.php`, `assets/js/admin/workforce.js` | `Manager Action Processing`, `Support Requests Table`, `Support Management Modal`, `Init Support Action Modal` |
| Admin charts | `assets/js/admin/overview.js`, `smart.js`, `hitl.js`, `workforce.js` | Search the named chart/render function |

## Folder Map

- `admin_portal/` - Admin authentication, dashboards, HITL, workforce, Smart Maintenance, technician management, and CSV endpoints.
- `technician_website/` - Mobile technician authentication, task lifecycle, history, support, and account settings.
- `includes/` - Shared PHP layouts, helper functions, database/business rules, and reusable UI components.
- `assets/css/` - Admin and presentation styling organized by module.
- `assets/js/` - Admin and presentation client-side behaviour organized by module.
- `config/` - Database, API, WhatsApp, and packaged database snapshot configuration.
- `smartops_fastapi/` - FastAPI service and SQL snapshot export logic. Generated `.venv` files are not stored in the project.
- `phpmyadmin_database/` - Shareable MySQL database snapshot.

## Source Files

### PHP

| File | Purpose |
|---|---|
| `admin_portal/admin_auth.php` | Admin session access control and identity helpers. |
| `admin_portal/api/csv_common.php` | Admin Portal / API: CSV Common server-side page, endpoint, or reusable module. |
| `admin_portal/api/hitl_csv.php` | Admin Portal / API: HITL CSV server-side page, endpoint, or reusable module. |
| `admin_portal/api/notifications.php` | Admin Portal / API: Notifications server-side page, endpoint, or reusable module. |
| `admin_portal/api/smart_maintenance_csv.php` | Admin Portal / API: Smart Maintenance CSV server-side page, endpoint, or reusable module. |
| `admin_portal/api/technicians_csv.php` | Admin Portal / API: Technicians CSV server-side page, endpoint, or reusable module. |
| `admin_portal/api/workforce_csv.php` | Admin Portal / API: Workforce CSV server-side page, endpoint, or reusable module. |
| `admin_portal/forgot_password.php` | Admin Portal: Forgot Password server-side page, endpoint, or reusable module. |
| `admin_portal/hitl/action.php` | HITL approval transaction, work-order creation, and Technical Specialist assignment. |
| `admin_portal/hitl/component_cases.php` | Admin Portal / HITL: Component Cases server-side page, endpoint, or reusable module. |
| `admin_portal/hitl/daily_category_summary.php` | Admin Portal / HITL: Daily Category Summary server-side page, endpoint, or reusable module. |
| `admin_portal/hitl/daily_component_cases.php` | Admin Portal / HITL: Daily Component Cases server-side page, endpoint, or reusable module. |
| `admin_portal/hitl/daily_summary.php` | Admin Portal / HITL: Daily Summary server-side page, endpoint, or reusable module. |
| `admin_portal/hitl/daily_summary_helpers.php` | Admin Portal / HITL: Daily Summary Helpers server-side page, endpoint, or reusable module. |
| `admin_portal/hitl/dashboard.php` | Human-in-the-Loop dashboard metrics, seven-day summaries, and escalation chart data. |
| `admin_portal/hitl/pending_review.php` | Admin Portal / HITL: Pending Review server-side page, endpoint, or reusable module. |
| `admin_portal/hitl/review_modal.php` | Reusable HITL case review and technician assignment modal. |
| `admin_portal/hitl/seven_day_cases.php` | Admin Portal / HITL: Seven Day Cases server-side page, endpoint, or reusable module. |
| `admin_portal/hitl/seven_day_summary.php` | Admin Portal / HITL: Seven Day Summary server-side page, endpoint, or reusable module. |
| `admin_portal/hitl/trigger_detail.php` | Admin Portal / HITL: Trigger Detail server-side page, endpoint, or reusable module. |
| `admin_portal/index.php` | Admin Portal: Index server-side page, endpoint, or reusable module. |
| `admin_portal/login.php` | Admin authentication page and login validation. |
| `admin_portal/logout.php` | Admin Portal: Logout server-side page, endpoint, or reusable module. |
| `admin_portal/notifications/open.php` | Admin Portal / Notifications: Open server-side page, endpoint, or reusable module. |
| `admin_portal/overview/dashboard.php` | Executive Overview dashboard UI and KPI/chart containers. |
| `admin_portal/overview/technician_capacity.php` | Admin Portal / Overview: Technician Capacity server-side page, endpoint, or reusable module. |
| `admin_portal/overview/trend_cases.php` | Admin Portal / Overview: Trend Cases server-side page, endpoint, or reusable module. |
| `admin_portal/settings.php` | Admin Portal: Settings server-side page, endpoint, or reusable module. |
| `admin_portal/signup.php` | Admin Portal: Signup server-side page, endpoint, or reusable module. |
| `admin_portal/smart/aging_detail.php` | Admin Portal / Smart: Aging Detail server-side page, endpoint, or reusable module. |
| `admin_portal/smart/asset_detail.php` | Admin Portal / Smart: Asset Detail server-side page, endpoint, or reusable module. |
| `admin_portal/smart/case_detail.php` | Admin Portal / Smart: Case Detail server-side page, endpoint, or reusable module. |
| `admin_portal/smart/component_cases.php` | Admin Portal / Smart: Component Cases server-side page, endpoint, or reusable module. |
| `admin_portal/smart/dashboard.php` | Smart Maintenance Intelligence dashboard UI and analytics containers. |
| `admin_portal/smart/trend_detail.php` | Admin Portal / Smart: Trend Detail server-side page, endpoint, or reusable module. |
| `admin_portal/technicians/action.php` | Technician Management create, update, and deactivate processing. |
| `admin_portal/technicians/index.php` | Technician Management directory and CRUD interface. |
| `admin_portal/workforce/action_support.php` | Manager processing for Parts, Senior Support, and Outsourcing requests. |
| `admin_portal/workforce/capacity_detail.php` | Admin Portal / Workforce: Capacity Detail server-side page, endpoint, or reusable module. |
| `admin_portal/workforce/dashboard.php` | Workforce and Task Monitoring dashboard UI and operational metrics. |
| `admin_portal/workforce/sla_department.php` | Admin Portal / Workforce: SLA Department server-side page, endpoint, or reusable module. |
| `admin_portal/workforce/status_detail.php` | Admin Portal / Workforce: Status Detail server-side page, endpoint, or reusable module. |
| `admin_portal/workforce/support_history.php` | Admin Portal / Workforce: Support History server-side page, endpoint, or reusable module. |
| `config/api.php` | FastAPI and external API endpoint configuration. |
| `config/db.php` | MySQL database connection configuration. |
| `config/demo_bootstrap.php` | Database bootstrap and packaged SQL snapshot version synchronization. |
| `config/whatsapp.php` | WhatsApp integration configuration values. |
| `includes/admin_layout.php` | Shared Admin Portal layout, sidebar navigation, KPI renderer, notification center, and page shell. |
| `includes/helpers.php` | Shared database helpers, business rules, SLA logic, case synchronization, and reusable utility functions. |
| `includes/technician_layout.php` | Shared Technician mobile layout, task display helpers, phone frame, navigation drawer, and page shell. |
| `index.php` | Index server-side entry point. |
| `technician_website/auth.php` | Technician Website: Auth server-side page, endpoint, or reusable module. |
| `technician_website/forgot_password.php` | Technician Website: Forgot Password server-side page, endpoint, or reusable module. |
| `technician_website/history.php` | Technician completed and released task history. |
| `technician_website/home.php` | Technician task workspace for Assigned, In Progress, Support, and Overdue work orders. |
| `technician_website/login.php` | Technician mobile login page inside the phone preview frame. |
| `technician_website/logout.php` | End the Technician session and return the user to the login page. |
| `technician_website/settings.php` | Technician profile, contact information, and password settings. |
| `technician_website/support_config.php` | Support request option configuration by hotel asset. |
| `technician_website/task_action.php` | Technician task lifecycle actions including accept, start, complete, and support requests. |

### JavaScript

| File | Purpose |
|---|---|
| `assets/js/admin/auth.js` | Js / Admin: Auth client-side interactions and dynamic UI behaviour. |
| `assets/js/admin/hitl.js` | Js / Admin: HITL client-side interactions and dynamic UI behaviour. |
| `assets/js/admin/live_clock.js` | Js / Admin: Live Clock client-side interactions and dynamic UI behaviour. |
| `assets/js/admin/notifications.js` | Js / Admin: Notifications client-side interactions and dynamic UI behaviour. |
| `assets/js/admin/overview.js` | Js / Admin: Overview client-side interactions and dynamic UI behaviour. |
| `assets/js/admin/settings.js` | Js / Admin: Settings client-side interactions and dynamic UI behaviour. |
| `assets/js/admin/smart.js` | Js / Admin: Smart client-side interactions and dynamic UI behaviour. |
| `assets/js/admin/technicians.js` | Js / Admin: Technicians client-side interactions and dynamic UI behaviour. |
| `assets/js/admin/workforce.js` | Js / Admin: Workforce client-side interactions and dynamic UI behaviour. |
| `assets/js/presentation.js` | Js: Presentation client-side interactions and dynamic UI behaviour. |
| `technician_website/assets/js/technician.js` | Technician Website / Js: Technician client-side interactions and dynamic UI behaviour. |

### CSS

| File | Purpose |
|---|---|
| `assets/css/admin/auth.css` | Css / Admin: Auth visual styling and responsive layout rules. |
| `assets/css/admin/dashboard_shared.css` | Css / Admin: Dashboard Shared visual styling and responsive layout rules. |
| `assets/css/admin/hitl.css` | Css / Admin: HITL visual styling and responsive layout rules. |
| `assets/css/admin/live_clock.css` | Css / Admin: Live Clock visual styling and responsive layout rules. |
| `assets/css/admin/notifications.css` | Css / Admin: Notifications visual styling and responsive layout rules. |
| `assets/css/admin/overview.css` | Css / Admin: Overview visual styling and responsive layout rules. |
| `assets/css/admin/overview_onepage.css` | Css / Admin: Overview Onepage visual styling and responsive layout rules. |
| `assets/css/admin/responsive.css` | Css / Admin: Responsive visual styling and responsive layout rules. |
| `assets/css/admin/settings.css` | Css / Admin: Settings visual styling and responsive layout rules. |
| `assets/css/admin/smart.css` | Css / Admin: Smart visual styling and responsive layout rules. |
| `assets/css/admin/technicians.css` | Css / Admin: Technicians visual styling and responsive layout rules. |
| `assets/css/admin/workforce.css` | Css / Admin: Workforce visual styling and responsive layout rules. |
| `assets/css/presentation.css` | Css: Presentation visual styling and responsive layout rules. |
| `technician_website/assets/css/technician.css` | Technician Website / Css: Technician visual styling and responsive layout rules. |

## Cleanup Notes

- Removed the packaged Python `.venv`, `__pycache__`, `.pyc`, macOS metadata, and ZIP metadata; these are generated files, not project source code.
- Retained all three real startup files: `START_FASTAPI_MAC.command`, `smartops_fastapi/run_mac.command`, and `smartops_fastapi/run_windows.bat`.
- Removed obsolete merge/reset text notes that no longer matched the final project structure.
- Removed the duplicate `.env.example`; the packaged `.env` already contains the blank/default local configuration used by the run scripts.
- Removed the unused Technician `profile.php` redirect because Account/Profile is now handled by `settings.php`.
- Removed the unused old HITL `asset_components.php` route; the current seven-day summary drill-through is handled by `seven_day_summary.php`.
- Replaced fixed historical CSS/JavaScript cache version labels with content-hash cache versions.
- Renamed internal legacy `smartstay_pdo()` and `SMARTSTAY_MAX_ACTIVE_TASKS` identifiers to `smartops_pdo()` and `SMARTOPS_MAX_ACTIVE_TASKS` without changing seeded login credentials.
- Consolidated confirmed obsolete HITL pulse overrides while keeping the current critical-case animation behaviour.

## Final Source Verification

- 61 PHP files: every file contains searchable `SECTION:` titles.
- 11 JavaScript files: every named function has a nearby `SECTION:` title.
- 14 CSS files: every stylesheet is divided into named functional sections.
- Long one-line SQL, HTML, and JavaScript blocks were reformatted into readable multi-line code.
- Exact duplicate source/config/document files: none found in the cleaned package.
- Remaining text/config documents are purposeful: `README.md`, this index, `requirements.txt`, `snapshot_version.txt`, and the active FastAPI `.env`.

## Developer Guide Tip

For screenshots, capture only the section title and the relevant 10-30 lines below it. This makes each screenshot explain one responsibility, such as **Database Connection**, **KPI Cards**, **Sidebar Navigation**, **HITL Assignment**, or **Technician Task Processing**.
