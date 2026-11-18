<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/dashboard_data.php';

$user = require_staff_or_admin();
if ($user['role'] === 'admin') {
    redirect('../admin/index.php');
}
$dashboard = get_dashboard_data($pdo, $user);
$isAdmin = false;
$basePath = '../';
$flashMessage = take_flash();
require __DIR__ . '/../includes/dashboard_view.php';
