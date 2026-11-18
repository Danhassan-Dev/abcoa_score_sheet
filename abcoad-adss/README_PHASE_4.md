# ABCOAD ADSS — Phase 4: Dynamic Dashboard

This package builds on Phase 3 and replaces the temporary administrator/staff landing pages with a database-driven dashboard using the original ABCOAD frontend styling.

## Dashboard features

- Live document totals from `score_sheets`.
- Live verified, pending and rejected status counts.
- Active department count from `departments`.
- Five most recent score-sheet uploads with course, session, uploader and status.
- Five most recent activity-log entries.
- Administrator sees institution-wide document and activity data.
- Staff sees only documents they uploaded and their own activity entries. The active department count remains an institution-wide reference figure.
- Responsive sidebar and role-aware navigation.
- Links to modules not yet implemented are visibly marked as coming in later phases and are disabled rather than pointing to non-existent pages.

## Installation / update

1. Back up your current `C:\xampp\htdocs\abcoad-adss` folder.
2. Extract the Phase 4 ZIP.
3. Copy/merge the extracted `abcoad-adss` folder into `C:\xampp\htdocs\`, allowing the Phase 4 files to replace their Phase 3 counterparts.
4. Keep your existing `config/database.php` credentials if you changed them for your local MySQL installation.
5. Do not import the database schema again if Phase 2 was already imported. Phase 4 uses the existing ten-table schema and adds no database tables or columns.
6. Start Apache and MySQL in XAMPP.
7. Sign in with the administrator account created in Phase 3 and open `http://localhost/abcoad-adss/admin/`.
8. Sign in with a staff account and open `http://localhost/abcoad-adss/staff/` to verify staff-specific dashboard data.

## Expected empty-state behaviour

If no score sheets or departments have been entered yet, dashboard counts should display `0` and the recent uploads/activity panels should show an empty-state message. This is expected; Phase 4 does not create sample examination records.

## Important

The dashboard reads from the database but does not yet upload, preview, download, search or verify score-sheet files. Those functions are scheduled for later phases. Do not use the dashboard as evidence that file upload or archive workflows are complete.
