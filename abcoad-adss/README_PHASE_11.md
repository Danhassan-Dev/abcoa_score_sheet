# ABCOAD ADSS — Phase 11

## Changes
- Added separate administrator sidebar links for Departments, Courses, Academic Sessions and Semesters.
- Semester creation/editing now uses only **First Semester** and **Second Semester**.
- Added an optional description field to each semester.
- Existing activation/deactivation, audit logging, CSRF protection and prepared statements are retained.

## Upgrade from Phase 10
1. Back up the project and `abcoad_adss` database.
2. Replace the project files with this Phase 11 package. Preserve your customized `config/database.php` and protected uploaded documents.
3. In phpMyAdmin, select `abcoad_adss` and run `database/phase11_migration.sql` once.
4. Sign in as administrator and use the new Academic Setup links in the sidebar.

Do not run this migration on a fresh database created from the updated `database/abcoad_adss.sql`, and do not run it twice.

## Validation
PHP syntax checks are included in the delivery process. Live XAMPP/MySQL/browser testing must be completed in the local environment.
