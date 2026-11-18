<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect_for_role(current_user());
}

$error = '';
$now = time();
$attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'first' => $now];
if (!is_array($attempts) || ($now - (int)($attempts['first'] ?? $now)) > 900) {
    $attempts = ['count' => 0, 'first' => $now];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ((int)$attempts['count'] >= 5) {
        $error = 'Too many unsuccessful attempts. Please wait 15 minutes before trying again.';
    } else {
        $identity = trim((string)($_POST['identity'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($identity === '' || $password === '') {
            $error = 'Enter your username/email and password.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, department_id, full_name, username, email, password_hash, role, status
                 FROM users WHERE username = :username_identity OR email = :email_identity LIMIT 1'
            );
            $stmt->execute(['username_identity' => $identity, 'email_identity' => $identity]);
            $account = $stmt->fetch();

            if ($account && $account['status'] === 'active' && password_verify($password, $account['password_hash'])) {
                session_regenerate_id(true);
                unset($_SESSION['login_attempts']);
                $_SESSION['user'] = [
                    'id' => (int)$account['id'],
                    'department_id' => $account['department_id'] !== null ? (int)$account['department_id'] : null,
                    'full_name' => $account['full_name'],
                    'username' => $account['username'],
                    'email' => $account['email'],
                    'role' => $account['role'],
                ];

                $update = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
                $update->execute(['id' => $account['id']]);
                log_activity($pdo, (int)$account['id'], 'login', 'user', (int)$account['id'], 'Successful sign-in');
                redirect_for_role($_SESSION['user']);
            }

            $attempts['count'] = (int)$attempts['count'] + 1;
            $attempts['first'] = (int)$attempts['first'];
            $_SESSION['login_attempts'] = $attempts;
            $error = 'Invalid credentials or account is inactive.';
        }
    }
}

$notice = take_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in | ABCOAD Digital Examination Score Sheet Archive</title>
  <link rel="stylesheet" href="assets/css/styles.css">
  <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
  <main class="auth-shell">
    <section class="auth-brand-panel">
      <img src="assets/images/logo.png" alt="Audu Bako College of Agriculture logo" class="auth-logo">
      <p class="auth-overline">AUDU BAKO COLLEGE OF AGRICULTURE, DAMBATTA</p>
      <h1>Digital Examination<br>Score Sheet Archive</h1>
      <p class="auth-description">A secure digital archive for preserving, organizing and retrieving official examination score sheets.</p>
      <div class="auth-trust"><span></span> Authorized college personnel only</div>
    </section>
    <section class="auth-form-panel">
      <div class="auth-form-wrap">
        <p class="form-eyebrow">WELCOME BACK</p>
        <h2>Sign in to your account</h2>
        <p class="form-subtitle">Use the account details provided by your system administrator.</p>
        <?php if ($notice): ?>
          <div class="notice <?= e($notice['type']) ?>"><?= e($notice['message']) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
          <div class="notice error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="login.php" autocomplete="on">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <label for="identity">Username or email address</label>
          <input id="identity" name="identity" type="text" autocomplete="username" required maxlength="150" value="<?= e((string)($_POST['identity'] ?? '')) ?>" placeholder="Enter your username or email">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password">
          <button class="auth-submit" type="submit">Sign in <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-help">Having trouble accessing your account? Contact your system administrator.</p>
        <div class="auth-footer">© <?= date('Y') ?> Audu Bako College of Agriculture, Dambatta</div>
      </div>
    </section>
  </main>
</body>
</html>
