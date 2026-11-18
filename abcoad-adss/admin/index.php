<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/dashboard_data.php';

$user = require_admin();
$dashboard = get_dashboard_data($pdo, $user);
$isAdmin = true;
$basePath = '../';
$verificationCentreUrl = 'verification.php';
$flashMessage = take_flash();
require __DIR__ . '/../includes/dashboard_view.php';
