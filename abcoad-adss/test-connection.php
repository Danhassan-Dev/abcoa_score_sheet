<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
$databaseName = $pdo->query('SELECT DATABASE()')->fetchColumn();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ADSS Database Test</title></head>
<body>
  <h1>Database connection successful!</h1>
  <p>Connected database: <strong><?= htmlspecialchars((string) $databaseName, ENT_QUOTES, 'UTF-8') ?></strong></p>
  <p>Delete this test file after confirming the connection.</p>
</body>
</html>
