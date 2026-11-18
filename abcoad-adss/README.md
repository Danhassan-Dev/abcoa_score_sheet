# ABCOAD Digital Examination Score Sheet Archive System (ADSS)

**Current milestone: Phase 9 — Testing and Deployment Readiness.**

A PHP 8+ / MySQL application for securely uploading, preserving, verifying, searching, previewing and retrieving original examination score-sheet files (PDF, JPG/JPEG and PNG). It does not calculate marks, grades or GPA, process results, or alter original score sheets.

## Technology
- PHP 8+, PDO and MySQL/MariaDB
- HTML5, CSS3 and vanilla JavaScript
- XAMPP for local development

## Local setup
1. Place `abcoad-adss` in `C:\xampp\htdocs\`.
2. Start Apache and MySQL from XAMPP.
3. Import `database/abcoad_adss.sql` through phpMyAdmin only for a fresh database. Do not re-import over a working database.
4. Check `config/database.php` or set the `ADSS_DB_*` environment variables.
5. Create the first administrator at `http://localhost/abcoad-adss/setup-admin.php` if no administrator exists.
6. Sign in at `http://localhost/abcoad-adss/login.php`.
7. Run `php deployment/preflight.php` from the project directory to check local deployment prerequisites.

## Main modules
- Administrator and staff authentication / role-based access
- Staff account administration
- Dynamic dashboards
- Secure score-sheet upload and protected file storage
- Searchable archive with preview and download
- Administrator verification centre
- Read-only activity log
- Institutional/support settings
- Deployment preflight and acceptance-test guide

## Documentation
- `README_PHASE_3.md` through `README_PHASE_9.md` document the implementation milestones.
- `README_PHASE_9.md` contains production deployment guidance and a full acceptance-test matrix.

## Deployment warning
The default storage directory is inside the project and protected by Apache access rules for local XAMPP use. For production, configure `ADSS_STORAGE_PATH` to an absolute directory outside the public web root, use HTTPS, a dedicated least-privilege database account, private backups and a documented restore procedure. Do not expose a development XAMPP installation directly to the internet.

## Roles and policy
The current database roles are `admin` and `staff`. HOD permissions have not been implemented because the institution's approval policy has not yet been confirmed. Do not upload real examination records until role permissions, retention and backup policies are agreed and acceptance tests pass.

## Phase 10 — Academic Data Management
See `README_PHASE_10.md`. Administrators manage departments, courses, academic sessions, semesters and examination types at `admin/academic-data.php`. Existing Phase 9 databases require the one-time `database/phase10_migration.sql` upgrade for semester activation status.


## Phase 11
See `README_PHASE_11.md` for the academic sidebar navigation and semester description changes.
