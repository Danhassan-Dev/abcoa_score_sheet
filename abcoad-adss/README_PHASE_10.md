# ABCOAD ADSS — Phase 10: Academic Data Management

## Features
- Administrator-only management of departments, courses, academic sessions, semesters and examination types.
- Add and edit records; activate/deactivate records without deleting historical references.
- Courses are assigned to an active department.
- Optional academic session start/end dates with date validation.
- Activity logging for create, update and status-change operations.
- Upload form reads active departments, courses, sessions, semesters and examination types from MySQL; server-side checks enforce active records and course/department relationships.
- Administrator dashboard navigation includes Academic Data.

## Install / upgrade
1. Back up the project folder and database.
2. Replace the project folder with this Phase 10 project, preserving any customized `config/database.php` and protected uploaded-document storage.
3. If upgrading an existing database from Phase 9, select `abcoad_adss` in phpMyAdmin and import `database/phase10_migration.sql` once. This adds the `status` column to `semesters`.
4. For a brand-new database, import `database/abcoad_adss.sql`; it already includes the semester status column. Do not run the migration on a fresh database.
5. Sign in as Administrator and open `http://localhost/abcoad-adss/admin/academic-data.php`.

## Recommended setup order
1. Departments: create each real department and a unique department code.
2. Courses: add course code/title and assign each to its department.
3. Academic sessions: add sessions such as `2025/2026` and optionally dates.
4. Semesters: add `First Semester` and `Second Semester` (or institution-approved labels).
5. Examination types: add institution-approved labels. This field remains optional on upload.
6. Assign staff accounts to their correct departments in Staff Management.
7. Test upload with a non-confidential sample document.

## Important behaviour
- No official ABCOAD department/course list is pre-seeded; the Administrator enters institution-approved records.
- Deactivate rather than delete records. Foreign keys protect records already referenced by archived documents.
- Inactive semesters are excluded from upload choices and rejected by server-side validation.
- Staff upload access remains restricted to their assigned department; administrators can upload for any active department.
- HOD role and approval permissions remain unimplemented pending an institutional policy decision.

## Validation
PHP syntax checks and ZIP integrity checks are performed during packaging. Live MySQL/browser tests must be completed in the user's XAMPP environment.
