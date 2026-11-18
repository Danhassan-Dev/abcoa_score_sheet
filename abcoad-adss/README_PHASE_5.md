# ABCOAD ADSS — Phase 5: Score Sheet Upload

## What's included
- Shared upload page for administrators and staff: `documents/upload.php`.
- Form fields for department, course, academic session, semester, optional examination type, lecturer, reference number and description.
- JPG, JPEG, PNG and PDF file upload with server-side extension, MIME and image-content validation.
- Configurable file-size cap from `system_settings.max_upload_size_mb` (defaults to 10 MB).
- Randomized storage filenames and SHA-256 file hash.
- Protected file storage under `storage/score-sheets/YYYY/MM/`.
- PDO insert into `score_sheets`, default Pending status, and activity log entry.
- Drag/drop selection, local preview, remove selection and XMLHttpRequest progress feedback.
- Dashboard upload navigation and primary action enabled.

## Install
1. Back up your current `C:\xampp\htdocs\abcoad-adss` folder.
2. Extract this ZIP and copy the contents of its `abcoad-adss` folder into `C:\xampp\htdocs\abcoad-adss`.
3. Keep your current `config/database.php` credentials if changed.
4. Ensure Apache and MySQL are running.
5. Sign in and choose **Upload Score Sheet**.

No SQL migration is required for Phase 5; the existing `score_sheets`, `system_settings`, and `activity_logs` tables are used.

## Required setup data
The upload form intentionally does not invent official college data. Before uploading, the database must contain:
- At least one active department.
- At least one active course linked to that department.
- At least one active academic session.
- At least one semester.
- An active examination type is optional.

These can be managed through phpMyAdmin for now. Department/session/course management is scheduled for Phase 8. For staff uploads, the staff account must be assigned to an active department and can only select courses in that department.

Example setting to change the maximum upload size:
```sql
INSERT INTO system_settings (setting_key, setting_value, description)
VALUES ('max_upload_size_mb', '10', 'Maximum permitted score-sheet upload size in MB')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
```
Use an integer from 1 to 100. The actual effective limit is also constrained by PHP's `upload_max_filesize` and `post_max_size` settings in `php.ini`.

## Storage and security
- Actual documents are saved beneath `storage/score-sheets/` using 48-character random hexadecimal names.
- `storage/.htaccess` denies direct HTTP access. Keep Apache `.htaccess` overrides enabled.
- The application stores the relative path, MIME type, byte size and SHA-256 digest in MySQL.
- The uploaded bytes are not resized, recompressed, converted, or edited.
- New records receive `pending` verification status.
- The upload event is written to `activity_logs`.
- Later archive preview/download must stream files through authorized PHP handlers; do not expose storage URLs.

## Notes
This phase implements upload and archive registration only. Search, preview and download through the application are Phase 6. Verification decisions are Phase 7. Do not store real institutional examination records until the installation and permissions have been tested.
