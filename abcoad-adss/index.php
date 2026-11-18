<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$user = current_user();
if ($user) {
    if ($user['role'] === 'admin') {
        redirect('admin/index.php');
    }
    redirect('staff/index.php');
}
redirect('login.php');
