<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();

$errors = [];
$form = ['full_name' => '', 'username' => '', 'email' => '', 'department_id' => ''];
$departments = $pdo->query("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $form = [
            'full_name' => trim((string)($_POST['full_name'] ?? '')),
            'username' => trim((string)($_POST['username'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'department_id' => trim((string)($_POST['department_id'] ?? '')),
        ];
        $password = (string)($_POST['password'] ?? '');
        if ($form['full_name'] === '' || mb_strlen($form['full_name']) > 150) $errors[] = 'Enter a valid full name.';
        if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $form['username'])) $errors[] = 'Username must be 3–60 characters using letters, numbers, dots, underscores or hyphens.';
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 150) $errors[] = 'Enter a valid email address.';
        if (strlen($password) < 12) $errors[] = 'Temporary password must contain at least 12 characters.';
        if ($form['department_id'] !== '' && !ctype_digit($form['department_id'])) $errors[] = 'Select a valid department.';

        if (!$errors) {
            try {
                $deptId = $form['department_id'] === '' ? null : (int)$form['department_id'];
                if ($deptId !== null) {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = :id AND status = 'active'");
                    $check->execute(['id' => $deptId]);
                    if (!(int)$check->fetchColumn()) throw new RuntimeException('The selected department is not active.');
                }
                $stmt = $pdo->prepare(
                    "INSERT INTO users (department_id, full_name, username, email, password_hash, role, status)
                     VALUES (:department_id, :full_name, :username, :email, :password_hash, 'staff', 'active')"
                );
                $stmt->execute([
                    'department_id' => $deptId,
                    'full_name' => $form['full_name'],
                    'username' => $form['username'],
                    'email' => $form['email'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                $newId = (int)$pdo->lastInsertId();
                log_activity($pdo, (int)$admin['id'], 'staff_created', 'user', $newId, 'Staff account created: ' . $form['username']);
                flash('success', 'Staff account created. Share the username and temporary password with the staff member securely.');
                redirect('users.php');
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            } catch (PDOException $exception) {
                error_log('ADSS staff creation: ' . $exception->getMessage());
                $errors[] = 'Could not create account. The username or email may already exist.';
            }
        }
    } elseif ($action === 'update') {
        $id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $department = trim((string)($_POST['department_id'] ?? ''));
        $status = (string)($_POST['status'] ?? '');
        if (!$id || $fullName === '' || mb_strlen($fullName) > 150) $errors[] = 'Enter a valid staff member and full name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) $errors[] = 'Enter a valid email address.';
        if (!in_array($status, ['active', 'inactive'], true)) $errors[] = 'Choose a valid account status.';
        if ($department !== '' && !ctype_digit($department)) $errors[] = 'Select a valid department.';

        if (!$errors) {
            try {
                $deptId = $department === '' ? null : (int)$department;
                if ($deptId !== null) {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = :id AND status = 'active'");
                    $check->execute(['id' => $deptId]);
                    if (!(int)$check->fetchColumn()) throw new RuntimeException('The selected department is not active.');
                }
                $stmt = $pdo->prepare(
                    "UPDATE users SET full_name = :full_name, email = :email, department_id = :department_id, status = :status
                     WHERE id = :id AND role = 'staff'"
                );
                $stmt->execute(['full_name' => $fullName, 'email' => $email, 'department_id' => $deptId, 'status' => $status, 'id' => $id]);
                if ($stmt->rowCount() === 0) {
                    $exists = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = :id AND role = 'staff'");
                    $exists->execute(['id' => $id]);
                    if (!(int)$exists->fetchColumn()) throw new RuntimeException('Staff account not found.');
                }
                log_activity($pdo, (int)$admin['id'], 'staff_updated', 'user', (int)$id, 'Staff profile or account status updated');
                flash('success', 'Staff account updated.');
                redirect('users.php');
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            } catch (PDOException $exception) {
                error_log('ADSS staff update: ' . $exception->getMessage());
                $errors[] = 'Could not update account. Check whether the email is already assigned to another user.';
            }
        }
    } elseif ($action === 'reset_password') {
        $id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $newPassword = (string)($_POST['new_password'] ?? '');
        if (!$id) $errors[] = 'Select a valid staff account.';
        if (strlen($newPassword) < 12) $errors[] = 'New password must contain at least 12 characters.';
        if (!$errors) {
            $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id AND role = 'staff'");
            $stmt->execute(['hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $id]);
            if ($stmt->rowCount() > 0) {
                log_activity($pdo, (int)$admin['id'], 'staff_password_reset', 'user', (int)$id, 'Staff password reset by administrator');
                flash('success', 'Staff password reset successfully. Share the new password securely.');
                redirect('users.php');
            }
            $errors[] = 'Staff account not found or password unchanged.';
        }
    } else {
        $errors[] = 'Invalid request.';
    }
}

$flashMessage = take_flash();
$staff = $pdo->query(
    "SELECT u.id, u.full_name, u.username, u.email, u.department_id, u.status, u.created_at,
            d.department_name
     FROM users u LEFT JOIN departments d ON d.id = u.department_id
     WHERE u.role = 'staff' ORDER BY u.created_at DESC"
)->fetchAll();
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff Management | ABCOAD ADSS</title><link rel="stylesheet" href="../assets/css/styles.css"><link rel="stylesheet" href="../assets/css/auth.css"></head>
<body class="app-page">
<header class="app-header"><div class="app-brand"><img src="../assets/images/logo.png" alt=""><div><b>AUDU BAKO COLLEGE OF AGRICULTURE, DAMBATTA</b><small>Digital Examination Score Sheet Archive</small></div></div>
<div class="header-user"><span><?= e($admin['full_name']) ?> · Administrator</span><form method="post" action="../logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="logout-button" type="submit">Sign out</button></form></div></header>
<main class="app-content">
  <a class="back-link" href="index.php">← Administrator workspace</a><p class="form-eyebrow">USER ADMINISTRATION</p><h1>Staff management</h1>
  <p class="form-subtitle">Only administrators can create staff accounts. There is no public registration page.</p>
  <?php if ($flashMessage): ?><div class="notice <?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div><?php endif; ?>
  <?php foreach ($errors as $message): ?><div class="notice error"><?= e($message) ?></div><?php endforeach; ?>

  <section class="workspace-panel">
    <div><h2>Create staff account</h2><p>Use a temporary password of at least 12 characters. Communicate credentials privately.</p></div>
    <form class="staff-form" method="post" action="users.php">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div><label for="new_full_name">Full name</label><input id="new_full_name" name="full_name" required maxlength="150" value="<?= e($form['full_name']) ?>"></div>
        <div><label for="new_username">Username</label><input id="new_username" name="username" required maxlength="60" pattern="[A-Za-z0-9._-]{3,60}" value="<?= e($form['username']) ?>"></div>
        <div><label for="new_email">Email</label><input id="new_email" name="email" type="email" required maxlength="150" value="<?= e($form['email']) ?>"></div>
        <div><label for="new_department">Department</label><select id="new_department" name="department_id"><option value="">Not assigned</option><?php foreach ($departments as $department): ?><option value="<?= (int)$department['id'] ?>" <?= $form['department_id'] === (string)$department['id'] ? 'selected' : '' ?>><?= e($department['department_name']) ?></option><?php endforeach; ?></select></div>
        <div class="form-wide"><label for="new_password">Temporary password (minimum 12 characters)</label><input id="new_password" name="password" type="password" required minlength="12" autocomplete="new-password"></div>
      </div>
      <button class="primary-link button-link" type="submit">Create staff account →</button>
    </form>
  </section>

  <section class="workspace-panel">
    <div class="section-heading"><div><h2>Registered staff</h2><p><?= count($staff) ?> staff account(s)</p></div></div>
    <?php if (!$staff): ?><p class="empty-state">No staff accounts have been created yet.</p><?php else: ?>
    <div class="staff-list">
    <?php foreach ($staff as $member): ?>
      <article class="staff-row">
        <div class="staff-person"><div class="staff-avatar"><?= e(strtoupper(substr($member['full_name'], 0, 1))) ?></div><div><b><?= e($member['full_name']) ?></b><small>@<?= e($member['username']) ?> · <?= e($member['email']) ?></small><small><?= e($member['department_name'] ?? 'No department assigned') ?></small></div></div>
        <span class="status-pill <?= $member['status'] === 'active' ? 'active' : 'inactive' ?>"><?= e(ucfirst($member['status'])) ?></span>
        <details class="manage-details"><summary>Manage account</summary>
          <form method="post" action="users.php" class="manage-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="user_id" value="<?= (int)$member['id'] ?>">
            <label>Full name<input name="full_name" required maxlength="150" value="<?= e($member['full_name']) ?>"></label>
            <label>Email<input name="email" type="email" required maxlength="150" value="<?= e($member['email']) ?>"></label>
            <label>Department<select name="department_id"><option value="">Not assigned</option><?php foreach ($departments as $department): ?><option value="<?= (int)$department['id'] ?>" <?= (string)$member['department_id'] === (string)$department['id'] ? 'selected' : '' ?>><?= e($department['department_name']) ?></option><?php endforeach; ?></select></label>
            <label>Account status<select name="status"><option value="active" <?= $member['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $member['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></label>
            <button class="secondary-button" type="submit">Save changes</button>
          </form>
          <form method="post" action="users.php" class="manage-form password-reset-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" value="<?= (int)$member['id'] ?>">
            <label>Set new password (minimum 12 characters)<input name="new_password" type="password" required minlength="12" autocomplete="new-password"></label>
            <button class="secondary-button" type="submit">Reset password</button>
          </form>
        </details>
      </article>
    <?php endforeach; ?>
    </div><?php endif; ?>
  </section>
</main></body></html>
