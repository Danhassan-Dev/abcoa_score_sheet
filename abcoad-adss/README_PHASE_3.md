# ABCOAD ADSS — Phase 3: Authentication and User Management

This ZIP is the complete project updated through Phase 3. It retains the Phase 2 database schema and the supplied frontend prototype assets.

## Phase 3 features
- Username or email sign-in.
- Password hashing with `password_hash()` and verification with `password_verify()`.
- Hardened PHP session cookies, session ID regeneration after login and POST-only logout.
- CSRF protection for state-changing forms.
- Role-based authorization for administrator and staff routes.
- One-time initial administrator creation; setup page disables itself once an administrator exists.
- Administrator can create staff accounts, assign an active department, edit staff details, activate/deactivate accounts and reset passwords.
- No public registration.
- Login/logout and staff account changes are written to `activity_logs`.
- Basic session-based login attempt throttling (five failed attempts per 15-minute window).

## Install / update
1. Back up your current `C:\xampp\htdocs\abcoad-adss` folder.
2. Extract this ZIP. Copy the contents of its `abcoad-adss` folder into `C:\xampp\htdocs\abcoad-adss`, allowing the Phase 3 files to merge/replace.
3. Keep your existing `config/database.php` credentials if you changed them for your MySQL setup.
4. Ensure the Phase 2 database `abcoad_adss` and its ten tables already exist. **Do not import the SQL again over a live database unless you know what you are doing.** The Phase 3 code uses the existing `users`, `departments`, and `activity_logs` tables; no schema migration is required.
5. Start Apache and MySQL in XAMPP.
6. Open `http://localhost/abcoad-adss/setup-admin.php`.
7. Create the first administrator using a unique password of at least 12 characters.
8. Sign in at `http://localhost/abcoad-adss/login.php`.
9. From the administrator workspace, open **Manage staff accounts** to create staff logins.

## Important account notes
- The setup page returns 404 after an administrator exists. Do not delete the administrator record to re-enable it.
- The system does not email or SMS credentials. Share initial or reset passwords with staff through an approved private channel.
- Inactive users cannot sign in.
- Staff cannot open administrator routes. A 403 response is expected if they try.
- Administrators can deactivate accounts instead of deleting them, preserving references needed by audit records and uploaded documents.
- Department choices come from the `departments` table. The project does not invent official college departments. If the table is empty, a staff account can be created with no department assigned and updated later.

## Test checklist
- [ ] Initial administrator setup succeeds once.
- [ ] Reopening `setup-admin.php` after setup returns 404.
- [ ] Administrator can sign in with username.
- [ ] Administrator can sign in with email.
- [ ] Wrong password shows a generic error.
- [ ] Inactive staff cannot sign in.
- [ ] Administrator can create a staff account.
- [ ] Administrator can edit staff name/email/department/status.
- [ ] Administrator can reset a staff password.
- [ ] Staff can sign in and see the staff workspace.
- [ ] Staff opening `/admin/users.php` receives 403.
- [ ] Logout returns to the login page.
- [ ] Activity records appear in `activity_logs`.

## Security / deployment notes
- Local XAMPP defaults are still in `config/database.php`; use a dedicated database account with limited privileges in production.
- Use HTTPS in deployment. Secure cookies are automatically enabled when PHP detects HTTPS.
- Login throttling here is session-based, not a shared IP/account lockout. Before public deployment, add a database-backed rate limiter, password reset workflow, administrator recovery procedure, and server-side monitoring.
- The initial setup endpoint should only be exposed during controlled installation. It is self-disabling after the first administrator is created.
- The preserved `prototype/` folder is a reference mockup and is not the authenticated application interface.
- Phase 3 does not yet implement score-sheet upload, archive search, document preview/download, verification workflow, dashboard archive metrics, or full activity-log browsing.
