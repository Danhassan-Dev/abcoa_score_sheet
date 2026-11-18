# ABCOAD ADSS — Phase 8

Phase 8 adds administrator-facing audit logs and system settings while retaining all Phase 1–7 functionality and the existing frontend branding.

## Added features

### Activity logs
- Administrator-only page: `admin/activity-logs.php`
- Lists recorded actions with actor, timestamp, record reference, details, IP address and user-agent information.
- Filters by keyword, action, user and date range.
- 25 records per page, newest first.
- Read-only interface; log records cannot be edited or deleted from this page.

### System settings
- Administrator-only page: `admin/settings.php`
- Maintains institution name, system title, support email, support phone and an administrative note.
- Uses the existing `system_settings` table and inserts safe defaults when a setting is missing.
- Validates email and field lengths; updates are transactional and activity-logged.
- These settings are informational and do not silently change upload limits, access rules, retention or verification policy.

### Navigation
- Administrator dashboard links now open Verification Centre, Activity Logs and Settings.
- Staff do not receive administrator-only navigation links.
- Dashboard activity panel links to the full audit log for administrators.

## Installation
1. Back up your current `abcoad-adss` project folder.
2. Extract this ZIP and replace the project folder in `C:\xampp\htdocs\`.
3. Preserve your existing `config/database.php` credentials if they differ from the supplied local defaults.
4. Start Apache and MySQL in XAMPP.
5. Sign in with an administrator account.
6. Open `http://localhost/abcoad-adss/admin/activity-logs.php` or `http://localhost/abcoad-adss/admin/settings.php`.

## Database
No SQL migration is required. Phase 2 already created `activity_logs` and `system_settings`. Settings defaults are inserted automatically the first time the settings page is opened.

## Security notes
- Both pages require an authenticated administrator.
- Settings writes use CSRF validation, a fixed allowlist of keys, validation, prepared statements and a transaction.
- Activity logs are displayed with HTML escaping and cannot be modified through the page.
- Activity logs can contain IP addresses and user-agent strings; restrict administrator accounts and protect database backups accordingly.

## Validation status
PHP syntax checks and ZIP integrity checks are performed for this package. Live testing against your local MySQL database and browser remains to be completed on your XAMPP installation.
