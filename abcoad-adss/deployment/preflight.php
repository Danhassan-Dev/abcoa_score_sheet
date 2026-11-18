<?php
declare(strict_types=1);

// Run from a terminal: php deployment/preflight.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$checks = [];
$checks[] = [PHP_VERSION_ID >= 80000, 'PHP version 8.0 or newer', PHP_VERSION];
$requiredExtensions = ['pdo', 'pdo_mysql', 'fileinfo', 'mbstring'];
$uploadLimit = ini_get('upload_max_filesize') ?: 'unknown';
$postLimit = ini_get('post_max_size') ?: 'unknown';
$checks[] = [true, 'PHP upload_max_filesize', $uploadLimit];
$checks[] = [true, 'PHP post_max_size', $postLimit . ' (must exceed upload_max_filesize)'];
foreach ($requiredExtensions as $extension) {
    $checks[] = [extension_loaded($extension), 'PHP extension: ' . $extension, extension_loaded($extension) ? 'Loaded' : 'Missing'];
}

if (!extension_loaded('pdo_mysql')) {
    foreach ($checks as [$ok, $name, $detail]) {
        echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . ' — ' . $detail . PHP_EOL;
    }
    echo PHP_EOL . 'Database checks skipped: enable pdo_mysql in the PHP configuration used by the CLI.' . PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/bootstrap.php';

$storagePath = adss_storage_root();
$storageExists = is_dir($storagePath);
$checks[] = [$storageExists, 'Private storage directory exists', $storagePath];
$checks[] = [$storageExists && is_writable($storagePath), 'Private storage directory writable', $storageExists ? (is_writable($storagePath) ? 'Writable' : 'Not writable') : 'Create the directory and grant the web-server account write access'];

$requiredTables = ['departments','users','academic_sessions','semesters','courses','examination_types','score_sheets','document_verifications','activity_logs','system_settings'];
foreach ($requiredTables as $table) {
    try {
        $pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
        $checks[] = [true, 'Database table: ' . $table, 'Present'];
    } catch (Throwable $exception) {
        $checks[] = [false, 'Database table: ' . $table, 'Missing or inaccessible'];
    }
}

try {
    $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'")->fetchColumn();
    $checks[] = [$admins > 0, 'Active administrator account', $admins > 0 ? $admins . ' active administrator(s)' : 'Create the initial administrator using setup-admin.php'];
} catch (Throwable $exception) {
    $checks[] = [false, 'Active administrator account', 'Could not query users table'];
}

$failures = 0;
foreach ($checks as [$ok, $name, $detail]) {
    if (!$ok) $failures++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . ' — ' . $detail . PHP_EOL;
}
echo PHP_EOL . ($failures === 0 ? 'Preflight completed: no blocking checks failed.' : $failures . ' check(s) failed. Resolve them before deployment.') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
