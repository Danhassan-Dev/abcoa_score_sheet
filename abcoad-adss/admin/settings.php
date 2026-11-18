<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();

$definitions = [
    'institution_name' => ['label' => 'Institution name', 'description' => 'Displayed institutional name for this archive.', 'max' => 180],
    'system_title' => ['label' => 'System title', 'description' => 'Name of the digital archive system.', 'max' => 120],
    'support_email' => ['label' => 'Support email', 'description' => 'Contact email shown to users who need assistance.', 'max' => 150],
    'support_phone' => ['label' => 'Support phone', 'description' => 'Contact phone number for archive support.', 'max' => 40],
    'records_note' => ['label' => 'Records administration note', 'description' => 'Internal guidance displayed on this settings page only; it does not change retention or deletion behaviour.', 'max' => 1000],
];
$defaults = [
    'institution_name' => 'Audu Bako College of Agriculture, Dambatta',
    'system_title' => 'Digital Examination Score Sheet Archive',
    'support_email' => '', 'support_phone' => '', 'records_note' => '',
];
$pdo->beginTransaction();
try {
    $seed = $pdo->prepare('INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES (:setting_key, :setting_value, :description)');
    foreach ($definitions as $key => $definition) $seed->execute(['setting_key'=>$key,'setting_value'=>$defaults[$key],'description'=>$definition['description']]);
    $pdo->commit();
} catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [];
    foreach ($definitions as $key => $definition) {
        $value = trim((string)($_POST[$key] ?? ''));
        if (mb_strlen($value) > $definition['max']) $errors[] = $definition['label'] . ' is too long.';
        if ($key === 'support_email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid support email address.';
        $values[$key] = $value;
    }
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $update = $pdo->prepare('UPDATE system_settings SET setting_value=:value, updated_by=:admin WHERE setting_key=:key');
            foreach ($values as $key => $value) $update->execute(['value'=>$value,'admin'=>(int)$admin['id'],'key'=>$key]);
            log_activity($pdo, (int)$admin['id'], 'system_settings_updated', 'system_settings', null, 'Updated institutional information and support contacts.');
            $pdo->commit();
            flash('success', 'System settings saved successfully.');
            redirect('settings.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('ADSS settings update failed: ' . $exception->getMessage());
            $errors[] = 'Settings could not be saved. Please try again.';
        }
    }
}
$stmt = $pdo->query('SELECT setting_key, setting_value, updated_at FROM system_settings WHERE setting_key IN (\'institution_name\',\'system_title\',\'support_email\',\'support_phone\',\'records_note\')');
$settings = [];
foreach ($stmt->fetchAll() as $row) $settings[$row['setting_key']] = $row;
$flashMessage = take_flash();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>System Settings | ABCOAD ADSS</title><link rel="stylesheet" href="../assets/css/styles.css"><link rel="stylesheet" href="../assets/css/auth.css"><style>.settings-wrap{max-width:900px;margin:28px auto;padding:0 22px}.settings-panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:22px;margin-top:18px}.settings-field{margin:17px 0}.settings-field label{display:block;font-weight:700;margin-bottom:7px}.settings-field input,.settings-field textarea{width:100%;box-sizing:border-box;padding:11px;border:1px solid #cbd5e1;border-radius:8px}.settings-field small{display:block;color:#64748b;margin-top:5px}.settings-field textarea{min-height:100px}.settings-alert{padding:12px;border-radius:8px;margin:12px 0;background:#f1f5f9}.settings-alert.error{background:#fee2e2;color:#991b1b}.settings-alert.success{background:#dcfce7;color:#166534}.settings-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.settings-actions a{color:#0b1f44}</style></head><body class="app-page"><header class="app-header"><div class="app-brand"><img src="../assets/images/logo.png" alt=""><div><b>AUDU BAKO COLLEGE OF AGRICULTURE, DAMBATTA</b><small>Digital Examination Score Sheet Archive</small></div></div><div class="header-user"><span><?= e($admin['full_name']) ?> · Administrator</span><form method="post" action="../logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="logout-button" type="submit">Sign out</button></form></div></header>
<main class="settings-wrap"><a href="index.php">← Administrator dashboard</a><h1>System settings</h1><p class="form-subtitle">Maintain institutional identity and support contact information for the archive.</p><?php if ($flashMessage): ?><div class="settings-alert <?= e((string)$flashMessage['type']) ?>"><?= e((string)$flashMessage['message']) ?></div><?php endif; ?><?php foreach ($errors as $error): ?><div class="settings-alert error"><?= e($error) ?></div><?php endforeach; ?>
<form class="settings-panel" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<?php foreach ($definitions as $key=>$definition): $value = $_POST && array_key_exists($key,$_POST) ? (string)$_POST[$key] : (string)($settings[$key]['setting_value'] ?? $defaults[$key]); ?><div class="settings-field"><label for="<?= e($key) ?>"><?= e($definition['label']) ?></label><?php if ($key==='records_note'): ?><textarea id="<?= e($key) ?>" name="<?= e($key) ?>" maxlength="<?= (int)$definition['max'] ?>"><?= e($value) ?></textarea><?php else: ?><input id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value) ?>" maxlength="<?= (int)$definition['max'] ?>" <?= $key==='support_email'?'type="email"':'' ?>><?php endif; ?><small><?= e($definition['description']) ?></small></div><?php endforeach; ?>
<p class="settings-actions"><button class="primary-link button-link" type="submit">Save settings</button><a href="activity-logs.php">View activity logs</a></p><p class="form-subtitle">Last updated values are stored in the existing system_settings table. These settings do not alter authentication, upload limits, retention, or verification permissions.</p></form></main></body></html>
