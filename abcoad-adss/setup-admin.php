<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($count > 0) {
    http_response_code(404);
    exit('Initial administrator setup is already complete. This page is disabled.');
}

$errors = [];
$fullName = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($fullName === '' || mb_strlen($fullName) > 150) $errors[] = 'Enter a valid full name (maximum 150 characters).';
    if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) $errors[] = 'Username must be 3–60 characters and use letters, numbers, dots, underscores or hyphens.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 12) $errors[] = 'Password must contain at least 12 characters.';
    if ($password !== $confirm) $errors[] = 'The password confirmation does not match.';

    // Re-check inside the request to prevent creating a second initial administrator.
    if (!$errors) {
        try {
            // MySQL named lock serializes the first-admin setup, including an empty users table.
            $gotLock = (int)$pdo->query("SELECT GET_LOCK('abcoad_adss_initial_admin', 10)")->fetchColumn();
            if ($gotLock !== 1) {
                throw new RuntimeException('Initial setup is busy. Please wait a moment and try again.');
            }
            $lock = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($lock > 0) {
                $pdo->query("SELECT RELEASE_LOCK('abcoad_adss_initial_admin')");
                http_response_code(404);
                exit('Initial administrator setup has already been completed.');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO users (department_id, full_name, username, email, password_hash, role, status)
                 VALUES (NULL, :full_name, :username, :email, :password_hash, 'admin', 'active')"
            );
            $stmt->execute([
                'full_name' => $fullName,
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $adminId = (int)$pdo->lastInsertId();
            log_activity($pdo, $adminId, 'initial_admin_created', 'user', $adminId, 'Initial administrator account created');
            $pdo->commit();
            $pdo->query("SELECT RELEASE_LOCK('abcoad_adss_initial_admin')");
            flash('success', 'Administrator account created. You can now sign in.');
            redirect('login.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            try { $pdo->query("SELECT RELEASE_LOCK('abcoad_adss_initial_admin')"); } catch (Throwable $ignored) {}
            error_log('ADSS initial admin setup: ' . $exception->getMessage());
            $errors[] = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Could not create the account. Check that the username and email are not already in use.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Initial Administrator Setup | ABCOAD ADSS</title>
  <link rel="stylesheet" href="assets/css/styles.css"><link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
<main class="setup-card">
  <img src="assets/images/logo.png" alt="Audu Bako College of Agriculture logo" class="setup-logo">
  <p class="form-eyebrow">ONE-TIME INITIAL SETUP</p>
  <h1>Create the first administrator</h1>
  <p class="form-subtitle">This page is available only while the database has no administrator account. Do not share this setup link.</p>
  <?php foreach ($errors as $message): ?><div class="notice error"><?= e($message) ?></div><?php endforeach; ?>
  <form method="post" action="setup-admin.php">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label for="full_name">Full name</label><input id="full_name" name="full_name" required maxlength="150" value="<?= e($fullName) ?>">
    <label for="username">Username</label><input id="username" name="username" required maxlength="60" pattern="[A-Za-z0-9._-]{3,60}" value="<?= e($username) ?>">
    <label for="email">Email address</label><input id="email" name="email" type="email" required maxlength="150" value="<?= e($email) ?>">
    <label for="password">Password (minimum 12 characters)</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password">
    <label for="confirm_password">Confirm password</label><input id="confirm_password" name="confirm_password" type="password" required minlength="12" autocomplete="new-password">
    <button class="auth-submit" type="submit">Create administrator <span aria-hidden="true">→</span></button>
  </form>
  <p class="auth-help">Use a unique password and store it securely. Staff accounts are created later by the administrator.</p>
</main>
</body></html>
