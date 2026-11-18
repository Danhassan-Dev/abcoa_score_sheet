<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'action' => trim((string)($_GET['action'] ?? '')),
    'user_id' => trim((string)($_GET['user_id'] ?? '')),
    'from' => trim((string)($_GET['from'] ?? '')),
    'to' => trim((string)($_GET['to'] ?? '')),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$where = [];
$params = [];
if ($filters['q'] !== '') {
    $where[] = '(al.action LIKE :q OR al.entity_type LIKE :q OR al.details LIKE :q OR u.full_name LIKE :q OR u.username LIKE :q OR al.ip_address LIKE :q)';
    $params['q'] = '%' . $filters['q'] . '%';
}
if ($filters['action'] !== '') { $where[] = 'al.action = :action'; $params['action'] = $filters['action']; }
if ($filters['user_id'] !== '' && ctype_digit($filters['user_id'])) { $where[] = 'al.user_id = :user_id'; $params['user_id'] = (int)$filters['user_id']; }
if ($filters['from'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from'])) { $where[] = 'al.created_at >= :from_date'; $params['from_date'] = $filters['from'] . ' 00:00:00'; }
if ($filters['to'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to'])) { $where[] = 'al.created_at <= :to_date'; $params['to_date'] = $filters['to'] . ' 23:59:59'; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$count = $pdo->prepare('SELECT COUNT(*) FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id' . $whereSql);
$count->execute($params);
$total = (int)$count->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$sql = 'SELECT al.id, al.user_id, al.action, al.entity_type, al.entity_id, al.details, al.ip_address, al.user_agent, al.created_at,
               u.full_name, u.username, u.role
        FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id' . $whereSql .
        ' ORDER BY al.created_at DESC, al.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
$stmt = $pdo->prepare($sql); $stmt->execute($params); $logs = $stmt->fetchAll();
$actions = $pdo->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$users = $pdo->query('SELECT id, full_name, username FROM users ORDER BY full_name')->fetchAll();
$flashMessage = take_flash();
function log_date(string $value): string { return date('d M Y, H:i:s', strtotime($value)); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Activity Logs | ABCOAD ADSS</title><link rel="stylesheet" href="../assets/css/styles.css"><style>
.audit-wrap{max-width:1400px;margin:28px auto;padding:0 22px}.audit-head{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}.audit-head h1{margin:5px 0}.audit-muted{color:#64748b;font-size:13px}.audit-panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px;margin:18px 0}.audit-filters{display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr auto;gap:10px;align-items:end}.audit-filters label{display:block;font-size:12px;font-weight:700;color:#334155}.audit-filters input,.audit-filters select{width:100%;box-sizing:border-box;margin-top:6px;padding:10px;border:1px solid #cbd5e1;border-radius:7px;background:#fff}.audit-filters button,.audit-button{padding:10px 14px;border:0;border-radius:7px;background:#0b1f44;color:#fff;text-decoration:none;cursor:pointer}.audit-table-wrap{overflow-x:auto}.audit-table{width:100%;border-collapse:collapse;min-width:920px}.audit-table th,.audit-table td{text-align:left;padding:12px 10px;border-bottom:1px solid #e8edf3;vertical-align:top;font-size:13px}.audit-table th{color:#64748b;font-size:11px;letter-spacing:.04em}.audit-details{max-width:360px;overflow-wrap:anywhere}.audit-pager{display:flex;gap:8px;align-items:center;margin-top:16px}.audit-pager a{padding:8px 12px;border:1px solid #dbe2ea;border-radius:7px;text-decoration:none;color:#0b1f44}.audit-flash{padding:12px;border-radius:8px;background:#f1f5f9;margin:12px 0}.audit-flash.error{background:#fee2e2;color:#991b1b}.audit-flash.success{background:#dcfce7;color:#166534}@media(max-width:900px){.audit-filters{grid-template-columns:1fr 1fr}.audit-filters .wide{grid-column:1/-1}}@media(max-width:560px){.audit-filters{grid-template-columns:1fr}}
</style></head><body class="app-page"><header class="app-header"><div class="app-brand"><img src="../assets/images/logo.png" alt=""><div><b>AUDU BAKO COLLEGE OF AGRICULTURE, DAMBATTA</b><small>Digital Examination Score Sheet Archive</small></div></div><div class="header-user"><span>Administrator</span><form method="post" action="../logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="logout-button" type="submit">Sign out</button></form></div></header>
<main class="audit-wrap"><div class="audit-head"><div><a href="index.php">← Administrator dashboard</a><h1>Activity logs</h1><p class="audit-muted">Audit trail of user and system actions. Records are read-only from this page.</p></div><a class="audit-button" href="settings.php">System settings</a></div>
<?php if ($flashMessage): ?><div class="audit-flash <?= e((string)$flashMessage['type']) ?>"><?= e((string)$flashMessage['message']) ?></div><?php endif; ?>
<section class="audit-panel"><form method="get" class="audit-filters">
<label class="wide">Search<input name="q" value="<?= e($filters['q']) ?>" placeholder="Action, user, details or IP address"></label>
<label>Action<select name="action"><option value="">All actions</option><?php foreach ($actions as $action): ?><option value="<?= e((string)$action) ?>" <?= $filters['action']===$action?'selected':'' ?>><?= e(str_replace('_',' ',(string)$action)) ?></option><?php endforeach; ?></select></label>
<label>User<select name="user_id"><option value="">All users</option><?php foreach ($users as $item): ?><option value="<?= (int)$item['id'] ?>" <?= $filters['user_id']===(string)$item['id']?'selected':'' ?>><?= e($item['full_name']) ?> (@<?= e($item['username']) ?>)</option><?php endforeach; ?></select></label>
<label>From<input type="date" name="from" value="<?= e($filters['from']) ?>"></label><label>To<input type="date" name="to" value="<?= e($filters['to']) ?>"></label><button type="submit">Apply filters</button></form></section>
<section class="audit-panel"><p class="audit-muted">Showing <?= count($logs) ?> of <?= number_format($total) ?> activity records · Page <?= $page ?> of <?= $pages ?></p><div class="audit-table-wrap"><table class="audit-table"><thead><tr><th>DATE & TIME</th><th>USER</th><th>ACTION</th><th>RECORD</th><th>DETAILS</th><th>IP ADDRESS</th></tr></thead><tbody>
<?php if (!$logs): ?><tr><td colspan="6">No activity records match these filters.</td></tr><?php else: foreach ($logs as $log): ?><tr><td><?= e(log_date((string)$log['created_at'])) ?></td><td><?= e($log['full_name'] ?? 'Deleted / system user') ?><br><span class="audit-muted"><?= e($log['username'] ? '@'.$log['username'] : 'No account') ?></span></td><td><strong><?= e(str_replace('_',' ',(string)$log['action'])) ?></strong></td><td><?= e($log['entity_type'] ?? '—') ?><?= $log['entity_id'] ? ' #'.(int)$log['entity_id'] : '' ?></td><td class="audit-details"><?= e($log['details'] ?? '—') ?><br><span class="audit-muted"><?= e($log['user_agent'] ?? '') ?></span></td><td><?= e($log['ip_address'] ?? '—') ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
<?php if ($pages>1): ?><nav class="audit-pager"><?php if ($page>1): ?><a href="?<?= e(http_build_query(array_merge($filters,['page'=>$page-1]))) ?>">Previous</a><?php endif; ?><span class="audit-muted">Page <?= $page ?> / <?= $pages ?></span><?php if ($page<$pages): ?><a href="?<?= e(http_build_query(array_merge($filters,['page'=>$page+1]))) ?>">Next</a><?php endif; ?></nav><?php endif; ?></section></main></body></html>
