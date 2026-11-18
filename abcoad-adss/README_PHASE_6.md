# ABCOAD ADSS — Phase 6: Document Archive

## Features
- Search archived records by original filename, lecturer, reference number, course code/title and uploader name (administrator view).
- Filter by verification status, academic session, semester and department (department filter is administrator-only).
- Paginated archive (15 records per page), newest uploads first.
- Metadata display: course, department, session, semester, examination type, lecturer, uploader, file type, size and status.
- Secure preview and download through `documents/file.php`.
- SHA-256 integrity verification before a file is served.
- Administrators can access all archived documents. Staff can access only documents they uploaded.
- Dashboard and upload navigation now link to the live archive.

## Installation
1. Back up the current `abcoad-adss` folder.
2. Extract this ZIP and copy the complete `abcoad-adss` folder contents into `C:\xampp\htdocs\abcoad-adss\`.
3. Preserve your existing `config/database.php` credentials if they differ from the package.
4. Start Apache and MySQL in XAMPP and sign in.
5. Open `http://localhost/abcoad-adss/documents/archive.php`.

No database migration is required. The archive uses the existing Phase 2 `score_sheets`, `courses`, `departments`, `academic_sessions`, `semesters`, `examination_types` and `users` tables.

## Access rules and HOD note
- `admin`: institution-wide archive access.
- `staff`: own uploaded records only.
- The current schema still supports only `admin` and `staff`. HOD role/department-wide access is not enabled yet because its verification and approval authority has not been confirmed. Do not manually change the users role enum before the role/permission migration is delivered.

## Integrity and storage
The file endpoint resolves the stored path under `storage/score-sheets`, rejects paths outside that directory, verifies the SHA-256 hash, and sends only PDF/JPEG/PNG files. Files are never transformed. A missing or modified stored file is not served.

For production deployment, place the storage directory outside the public web root and update the storage path configuration accordingly. The current package retains the XAMPP-local protected directory and its `.htaccess` protection.

## Testing checklist
- Search by filename, lecturer, course code and reference number.
- Filter by pending, verified and rejected status.
- Filter by session and semester; administrator can also filter by department.
- Confirm pagination preserves filters.
- Preview a PDF, JPG and PNG.
- Download a PDF, JPG and PNG.
- Sign in as staff and confirm another user's document cannot be accessed by changing the document ID in the URL.
- As administrator, confirm institution-wide document access.
- Modify a test file in storage and confirm the integrity check refuses to serve it; restore the original file afterward.
- Confirm an empty archive displays a useful message.

The package has been checked for PHP syntax and ZIP integrity. Live database/browser testing must be completed on the local XAMPP installation.
