# ABCOAD ADSS — Phase 7: Verification Centre

## Implemented
- Administrator-only verification centre at `admin/verification.php`.
- Queue tabs for pending, verified, rejected and all records.
- Search by filename, lecturer, course code/title and reference number.
- Original document preview/download using the protected Phase 6 file handler, including its SHA-256 integrity check.
- Verify and reject actions for pending documents.
- Mandatory rejection reason; optional review comments.
- Database transaction locks the score-sheet row, prevents a second decision on an already-reviewed item, updates current status and inserts a row in `document_verifications`.
- Reviewer identity and timestamp are preserved in the verification history table.
- Activity log entry for each successful decision.
- CSRF validation and output escaping.

## Installation
1. Back up the current `abcoad-adss` directory.
2. Extract this ZIP and copy the complete `abcoad-adss` folder into `C:\xampp\htdocs\`.
3. Preserve your working `config/database.php` credentials.
4. Start Apache and MySQL, sign in as an administrator.
5. Open `http://localhost/abcoad-adss/admin/verification.php`.

No database migration is required. Phase 2 already created `document_verifications` with the required columns and foreign keys.

## Authorization decision
Only the existing `admin` role can verify or reject documents in this phase. The existing database enum supports `admin` and `staff`; HOD role and departmental verification authority have not been added because that permission policy is still awaiting confirmation. Do not manually alter the role enum. A later role migration should explicitly define whether HOD can approve/reject within their department.

## Test checklist
- Sign in as admin and open the verification centre.
- Confirm pending documents appear and non-pending documents are not actionable.
- Preview PDF, JPG and PNG documents.
- Verify one pending document; confirm status changes and a history row is inserted.
- Reject another pending document without a reason; confirm it is blocked.
- Reject with a reason; confirm status, reviewer, reason and timestamp are recorded.
- Try submitting a decision twice; confirm the second decision is refused.
- Sign in as staff and attempt to open `admin/verification.php`; confirm HTTP 403.
- Confirm audit entries are written to `activity_logs`.

PHP syntax and ZIP integrity should be checked after packaging. Live MySQL/browser testing remains necessary on the local XAMPP installation.
