# ABCOAD ADSS — Phase 9: Testing and Deployment Readiness

Phase 9 adds deployment preflight checks, production storage configuration and a structured acceptance-test/deployment guide. It preserves the existing PHP/MySQL application and does not calculate, grade or alter examination scores.

## Phase 9 changes

### 1. Configurable private document storage
- New uploads are stored under a private storage root, in `YYYY/MM/` subdirectories.
- Default local XAMPP location remains `abcoad-adss/storage/score-sheets/`.
- Production can set `ADSS_STORAGE_PATH` to an absolute directory outside the public web root.
- The document-serving endpoint validates the resolved path remains inside the configured storage root and rejects traversal/absolute paths.
- Existing database records that contain the earlier `storage/score-sheets/YYYY/MM/...` path format remain readable after moving the existing storage contents to the configured root.
- SHA-256 integrity checks and role/ownership authorization remain in force.

### 2. Environment-based database configuration
`config/database.php` now supports:
- `ADSS_DB_HOST`
- `ADSS_DB_NAME`
- `ADSS_DB_USER`
- `ADSS_DB_PASS`

If these are not set, local XAMPP defaults are retained (`127.0.0.1`, `abcoad_adss`, `root`, empty password). For production, use a dedicated database account, not `root`.

### 3. CLI deployment preflight
Run from the project directory:

```bash
php deployment/preflight.php
```

The script checks PHP extensions, private storage directory availability/permissions, required database tables and the presence of an active administrator. It is CLI-only and returns a non-zero exit code if a blocking check fails.

## Local XAMPP update
1. Back up your current `abcoad-adss` folder and database first.
2. Extract this ZIP and replace the project folder in `C:\xampp\htdocs\`.
3. Keep your existing `config/database.php` changes if you have custom database credentials, or configure the environment variables described above.
4. Keep the default `storage/score-sheets` directory and ensure Apache can write to it.
5. Start Apache and MySQL.
6. Run `php deployment/preflight.php` from a terminal opened in the project folder. The CLI PHP must have `pdo_mysql`, `fileinfo` and `mbstring` enabled. XAMPP's Apache PHP and CLI PHP can load different `php.ini` files; verify both.
7. Visit `http://localhost/abcoad-adss/login.php` and complete the acceptance tests below.

No SQL migration is required for Phase 9.

## Production deployment checklist

Do not expose a development XAMPP installation directly to the public internet. Before production:

1. Deploy behind HTTPS and configure secure session cookies; use a supported PHP 8.x release and maintained MySQL/MariaDB version.
2. Set `ADSS_DB_HOST`, `ADSS_DB_NAME`, `ADSS_DB_USER` and `ADSS_DB_PASS` in the web-server/PHP environment. Use a dedicated database account restricted to this application's database. Grant runtime permissions only as required (normally SELECT, INSERT and UPDATE); perform schema changes using a separate deployment account.
3. Set `ADSS_STORAGE_PATH` to an absolute directory outside the document root, for example `D:/abcoad-private/score-sheets` on Windows or `/srv/abcoad-private/score-sheets` on Linux. Create it first and grant write access only to the web-server service account and authorized system administrators.
4. Copy the contents of the old `storage/score-sheets/` directory into the configured external storage root, preserving the year/month subdirectories. Existing database paths are backward-compatible. Do not delete the old copy until file preview/download has been tested against representative older records and the backup has been verified.
5. Configure PHP `upload_max_filesize` and `post_max_size` so both are at least the intended application upload limit; `post_max_size` must be larger than `upload_max_filesize`. Confirm temporary upload storage and private archive storage have sufficient disk space.
6. Disable directory listing, restrict filesystem permissions, protect configuration/secrets, and remove or deny access to setup/diagnostic scripts after installation (including `setup-admin.php` and `test-connection.php`). The initial-admin page self-disables once an administrator exists, but removing/denying it is still recommended.
7. Configure database and file backups, off-site backup copies, retention policy approved by the institution, restore drills and disk-space monitoring. Back up database records and the private document directory together.
8. Restrict administrator accounts, enforce unique strong passwords, review staff access, and agree on who is authorized to verify/reject records before operational use.
9. Run the full acceptance test plan below in a staging environment before importing real institutional records.

## Acceptance test plan

Use dummy records and non-sensitive sample files only. Record pass/fail, tester, date and notes for each test.

| ID | Test | Expected result |
|---|---|---|
| AUTH-01 | Sign in with valid active administrator credentials | Administrator dashboard opens |
| AUTH-02 | Sign in with invalid credentials | Access denied without revealing whether username/email exists |
| AUTH-03 | Sign in with inactive account | Access denied |
| AUTH-04 | Open an administrator URL as staff | HTTP 403 / access denied |
| AUTH-05 | Sign out using the form | Session ends; protected pages require sign-in |
| USER-01 | Administrator creates staff account | Account is saved with a password hash and selected department |
| USER-02 | Staff attempts to open user management | Access denied |
| UP-01 | Upload a valid PDF/JPG/JPEG/PNG with valid metadata | Original file is stored; metadata is recorded; status is pending |
| UP-02 | Upload a renamed executable or mismatched MIME file | Rejected; no archive record is created |
| UP-03 | Upload an empty or oversized file | Rejected with a useful message |
| UP-04 | Staff submits a course from another department | Rejected server-side |
| ARC-01 | Search/filter archive and move between pages | Matching records appear; pagination works |
| ARC-02 | Staff requests another user's document ID | HTTP 403 / access denied |
| ARC-03 | Alter an archived file after upload, then preview it | Integrity check blocks delivery |
| VER-01 | Administrator verifies a pending record | Status changes to verified and reviewer history is recorded |
| VER-02 | Administrator rejects without a reason | Request is rejected by validation |
| VER-03 | Administrator rejects with a reason | Status and reviewer/reason history are recorded |
| VER-04 | Attempt to decide a record that is no longer pending | No duplicate decision is recorded |
| LOG-01 | Perform login, upload, verification and settings actions | Corresponding audit records appear |
| SET-01 | Save valid settings and refresh | Values persist |
| SET-02 | Submit malformed support email | Validation blocks save |
| DEP-01 | Run CLI preflight with required configuration | Required extensions, tables and storage checks pass |
| DEP-02 | Configure external storage and upload/download a dummy file | File is written and served only through the authenticated endpoint |
| DEP-03 | Restore a database and file backup in staging | Records and files are retrievable and hashes match |

## Current verification status

- PHP syntax validation is performed for all application PHP files in this package.
- ZIP integrity is checked before delivery.
- The deployment preflight is included but must be run in the user's XAMPP environment.
- This development environment does not have the MySQL PDO driver enabled, so live database integration tests could not be run here.
- Browser-based role, upload, preview, verification and settings acceptance tests remain to be completed on the user's machine.

## Important operational decisions still required

- Confirm whether HOD accounts should exist and whether HODs may verify records for their own departments. Current schema and access rules support only `admin` and `staff`; Phase 9 does not invent HOD permissions.
- Agree institutional retention, backup, restore and approval policies before real examination records are uploaded.
- No marks calculation, GPA computation, grading, result processing or alteration of original score sheets is performed by this system.
