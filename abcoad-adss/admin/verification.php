<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_admin();

function verification_redirect(string $status = 'pending'): never {
    redirect('verification.php?status=' . rawurlencode($status));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $documentId = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);
    $decision = (string)($_POST['decision'] ?? '');
    $comments = trim((string)($_POST['comments'] ?? ''));
    $reason = trim((string)($_POST['rejection_reason'] ?? ''));
    if (!$documentId || !in_array($decision, ['verified', 'rejected'], true)) {
        flash('error', 'Invalid verification request.');
        verification_redirect();
    }
    if ($decision === 'rejected' && $reason === '') {
        flash('error', 'A rejection reason is required.');
        verification_redirect();
    }
    if (mb_strlen($reason) > 5000 || mb_strlen($comments) > 5000) {
        flash('error', 'The reason or comments are too long.');
        verification_redirect();
    }

    try {
        $pdo->beginTransaction();
        $lock = $pdo->prepare('SELECT id, verification_status FROM score_sheets WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $documentId]);
        $document = $lock->fetch();
        if (!$document) {
            $pdo->rollBack();
            flash('error', 'The requested score sheet was not found.');
            verification_redirect();
        }
        if ($document['verification_status'] !== 'pending') {
            $pdo->rollBack();
            flash('error', 'This score sheet has already been reviewed.');
            verification_redirect((string)$document['verification_status']);
        }
        $update = $pdo->prepare('UPDATE score_sheets SET verification_status = :decision WHERE id = :id');
        $update->execute(['decision' => $decision, 'id' => $documentId]);
        $history = $pdo->prepare(
            'INSERT INTO document_verifications (score_sheet_id, reviewed_by, decision, rejection_reason, comments)
             VALUES (:document_id, :reviewer, :decision, :reason, :comments)'
        );
        $history->execute([
            'document_id' => $documentId,
            'reviewer' => (int)$user['id'],
            'decision' => $decision,
            'reason' => $decision === 'rejected' ? $reason : null,
            'comments' => $comments !== '' ? $comments : null,
        ]);
        log_activity($pdo, (int)$user['id'], 'score_sheet_' . $decision, 'score_sheet', $documentId,
            $decision === 'rejected' ? 'Rejection reason: ' . $reason : 'Score sheet verified.');
        $pdo->commit();
        flash('success', 'Score sheet #' . $documentId . ' was ' . $decision . ' successfully.');
        verification_redirect();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('ADSS verification failed: ' . $exception->getMessage());
        flash('error', 'The review could not be saved. Please try again.');
        verification_redirect();
    }
}

$status = (string)($_GET['status'] ?? 'pending');
if (!in_array($status, ['pending', 'verified', 'rejected', 'all'], true)) $status = 'pending';
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$where = [];
$params = [];
if ($status !== 'all') { $where[] = 'ss.verification_status = :status'; $params['status'] = $status; }
if ($q !== '') {
    $where[] = '(ss.original_filename LIKE :q OR ss.lecturer_name LIKE :q OR ss.reference_number LIKE :q OR c.course_code LIKE :q OR c.course_title LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$count = $pdo->prepare('SELECT COUNT(*) FROM score_sheets ss JOIN courses c ON c.id=ss.course_id' . $whereSql);
$count->execute($params);
$total = (int)$count->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$sql = 'SELECT ss.id, ss.original_filename, ss.lecturer_name, ss.reference_number, ss.mime_type, ss.file_size,
               ss.verification_status, ss.created_at, c.course_code, c.course_title, d.department_name,
               ac.session_name, sm.semester_name, u.full_name AS uploader
        FROM score_sheets ss
        JOIN courses c ON c.id=ss.course_id
        JOIN departments d ON d.id=c.department_id
        JOIN academic_sessions ac ON ac.id=ss.academic_session_id
        JOIN semesters sm ON sm.id=ss.semester_id
        JOIN users u ON u.id=ss.uploaded_by' . $whereSql . '
        ORDER BY CASE ss.verification_status WHEN \'pending\' THEN 0 ELSE 1 END, ss.created_at DESC
        LIMIT ' . $perPage . ' OFFSET ' . $offset;
$stmt = $pdo->prepare($sql); $stmt->execute($params); $documents = $stmt->fetchAll();
$flashMessage = take_flash();
function status_badge(string $status): string {
    return match ($status) {
        'verified' => 'Verified', 'rejected' => 'Rejected', default => 'Pending'
    };
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verification Centre | ABCOAD ADSS</title>
<link rel="stylesheet" href="../assets/css/styles.css"><style>
.verify-wrap{max-width:1250px;margin:32px auto;padding:0 22px}.verify-head{display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap}.verify-head h1{margin:0}.verify-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:22px 0}.verify-tabs a,.action-link{padding:9px 13px;border:1px solid #d9e0e9;border-radius:8px;text-decoration:none;color:#14243b;background:#fff}.verify-tabs a.active{background:#0b1f44;color:#fff}.verify-card{background:#fff;border:1px solid #e1e6ed;border-radius:12px;padding:18px;margin:14px 0}.verify-meta{color:#64748b;font-size:13px;line-height:1.7}.verify-status{font-weight:700;text-transform:capitalize}.verify-actions{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px}.verify-actions textarea{width:100%;min-height:72px;padding:10px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}.verify-actions button{border:0;border-radius:8px;padding:10px 14px;cursor:pointer;font-weight:700}.approve{background:#dff5e8;color:#12623a}.reject{background:#fde8e8;color:#9b2020}.flash{padding:12px 15px;border-radius:8px;background:#f1f5f9;margin:16px 0}.flash.error{background:#fee2e2;color:#991b1b}.flash.success{background:#dcfce7;color:#166534}.verify-search{display:flex;gap:8px;flex-wrap:wrap}.verify-search input{padding:10px;border:1px solid #cbd5e1;border-radius:8px;min-width:240px}.verify-search button{padding:10px 15px;border:0;border-radius:8px;background:#0b1f44;color:#fff}@media(max-width:650px){.verify-actions{grid-template-columns:1fr}.verify-wrap{padding:0 12px}}
</style></head><body>
<div class="verify-wrap"><div class="verify-head"><div><p class="verify-meta"><a href="index.php">← Administrator dashboard</a></p><h1>Verification Centre</h1><p class="verify-meta">Review uploaded score sheets and record an auditable decision.</p></div><a class="action-link" href="../documents/archive.php">Open document archive</a></div>
<?php if ($flashMessage): ?><div class="flash <?= e((string)$flashMessage['type']) ?>"><?= e((string)$flashMessage['message']) ?></div><?php endif; ?>
<nav class="verify-tabs">
<?php foreach (['pending'=>'Pending','verified'=>'Verified','rejected'=>'Rejected','all'=>'All records'] as $key=>$label): ?><a class="<?= $status===$key?'active':'' ?>" href="?status=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?>
</nav>
<form class="verify-search" method="get"><input type="hidden" name="status" value="<?= e($status) ?>"><input name="q" value="<?= e($q) ?>" placeholder="Search filename, lecturer, course or reference"><button type="submit">Search</button></form>
<p class="verify-meta">Showing <?= count($documents) ?> of <?= $total ?> records · Page <?= $page ?> of <?= $pages ?></p>
<?php if (!$documents): ?><div class="verify-card">No score sheets match this view.</div><?php endif; ?>
<?php foreach ($documents as $doc): ?>
<article class="verify-card"><div class="verify-head"><div><strong>#<?= (int)$doc['id'] ?> — <?= e((string)$doc['original_filename']) ?></strong><div class="verify-meta"><?= e((string)$doc['course_code']) ?> · <?= e((string)$doc['course_title']) ?> · <?= e((string)$doc['department_name']) ?></div></div><span class="verify-status"><?= e(status_badge((string)$doc['verification_status'])) ?></span></div>
<div class="verify-meta">Lecturer: <?= e((string)$doc['lecturer_name']) ?> · Uploaded by: <?= e((string)$doc['uploader']) ?><br>Session: <?= e((string)$doc['session_name']) ?> · <?= e((string)$doc['semester_name']) ?> · <?= e(number_format(((int)$doc['file_size'])/1024,1)) ?> KB · <?= e(date('d M Y, H:i', strtotime((string)$doc['created_at']))) ?></div>
<p><a class="action-link" target="_blank" rel="noopener" href="../documents/file.php?id=<?= (int)$doc['id'] ?>&amp;mode=preview">Preview original document</a> <a class="action-link" href="../documents/file.php?id=<?= (int)$doc['id'] ?>&amp;mode=download">Download</a></p>
<?php if ($doc['verification_status']==='pending'): ?><form method="post" class="verify-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
<div><label for="reason-<?= (int)$doc['id'] ?>">Rejection reason (required to reject)</label><textarea id="reason-<?= (int)$doc['id'] ?>" name="rejection_reason" maxlength="5000" placeholder="Explain why this score sheet is rejected"></textarea></div>
<div><label for="comments-<?= (int)$doc['id'] ?>">Review comments (optional)</label><textarea id="comments-<?= (int)$doc['id'] ?>" name="comments" maxlength="5000" placeholder="Additional review notes"></textarea><div style="display:flex;gap:8px;margin-top:10px"><button class="approve" name="decision" value="verified" onclick="return confirm('Verify this score sheet?')">Verify score sheet</button><button class="reject" name="decision" value="rejected" onclick="return confirm('Reject this score sheet?')">Reject score sheet</button></div></div></form><?php endif; ?></article>
<?php endforeach; ?>
<?php if ($pages>1): ?><div class="verify-tabs"><?php if ($page>1): ?><a href="?status=<?= e($status) ?>&amp;q=<?= rawurlencode($q) ?>&amp;page=<?= $page-1 ?>">Previous</a><?php endif; ?><?php if ($page<$pages): ?><a href="?status=<?= e($status) ?>&amp;q=<?= rawurlencode($q) ?>&amp;page=<?= $page+1 ?>">Next</a><?php endif; ?></div><?php endif; ?>
</div></body></html>
