<?php
declare(strict_types=1);

/**
 * Shared application bootstrap for ABCOAD ADSS.
 * Starts a hardened session, connects to MySQL and loads common helpers.
 */
$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('ABCOAD_ADSS_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Returns the private score-sheet storage directory. Set ADSS_STORAGE_PATH
 * to an absolute path outside the public web root for production deployments.
 */
function adss_storage_root(): string
{
    $configured = getenv('ADSS_STORAGE_PATH');
    $path = ($configured !== false && trim((string)$configured) !== '')
        ? trim((string)$configured)
        : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'score-sheets';

    $isAbsolute = str_starts_with($path, DIRECTORY_SEPARATOR)
        || (DIRECTORY_SEPARATOR === '\\' && (bool)preg_match('/^[A-Za-z]:[\\\\\/]/', $path));
    if (!$isAbsolute) {
        throw new RuntimeException('ADSS_STORAGE_PATH must be an absolute directory path.');
    }
    return rtrim($path, '/\\');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = (string)($_POST['csrf_token'] ?? '');
    $known = (string)($_SESSION['csrf_token'] ?? '');
    if ($known === '' || !hash_equals($known, $submitted)) {
        http_response_code(419);
        exit('Your form session has expired. Go back, refresh the page and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($message) ? $message : null;
}

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please sign in to continue.');
        redirect('../login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Access denied. Administrator permission is required.');
    }
    return $user;
}

function require_staff_or_admin(): array
{
    $user = require_login();
    if (!in_array($user['role'] ?? '', ['admin', 'staff'], true)) {
        http_response_code(403);
        exit('Access denied.');
    }
    return $user;
}

function log_activity(PDO $pdo, ?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
         VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip, :agent)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'details' => $details,
        'ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        'agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    ]);
}

function redirect_for_role(array $user): never
{
    if (($user['role'] ?? '') === 'admin') {
        redirect('admin/index.php');
    }
    redirect('staff/index.php');
}
