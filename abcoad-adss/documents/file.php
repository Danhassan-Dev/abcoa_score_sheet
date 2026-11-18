<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_staff_or_admin();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$mode = (string)($_GET['mode'] ?? 'preview');
if (!$id || !in_array($mode, ['preview', 'download'], true)) { http_response_code(400); exit('Invalid document request.'); }
$sql = 'SELECT ss.*, c.department_id FROM score_sheets ss JOIN courses c ON c.id=ss.course_id WHERE ss.id=:id LIMIT 1';
$stmt = $pdo->prepare($sql); $stmt->execute(['id'=>$id]); $doc = $stmt->fetch();
if (!$doc) { http_response_code(404); exit('Document not found.'); }
if (($user['role'] ?? '') !== 'admin' && (int)$doc['uploaded_by'] !== (int)$user['id']) { http_response_code(403); exit('You are not authorized to access this document.'); }
$storageRoot = realpath(adss_storage_root());
$relativePath = str_replace('\\', '/', (string)$doc['file_path']);
// Backward compatibility for records uploaded before Phase 9.
if (str_starts_with($relativePath, 'storage/score-sheets/')) {
    $relativePath = substr($relativePath, strlen('storage/score-sheets/'));
}
$pathParts = explode('/', $relativePath);
if ($relativePath === '' || str_starts_with($relativePath, '/') || preg_match('/^[A-Za-z]:/', $relativePath) || in_array('..', $pathParts, true)) {
    http_response_code(404); exit('The archived file is unavailable. Contact the administrator.');
}
$fullPath = $storageRoot ? realpath($storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath)) : false;
if (!$storageRoot || !$fullPath || !str_starts_with($fullPath, $storageRoot . DIRECTORY_SEPARATOR) || !is_file($fullPath)) { error_log('ADSS archive file missing or outside storage for record '.$id); http_response_code(404); exit('The archived file is unavailable. Contact the administrator.'); }
$actualHash = hash_file('sha256', $fullPath);
if (!is_string($actualHash) || !hash_equals((string)$doc['file_hash'], $actualHash)) { error_log('ADSS integrity check failed for record '.$id); http_response_code(409); exit('Document integrity verification failed. The file has not been served. Contact the administrator.'); }
$allowed = ['application/pdf', 'image/jpeg', 'image/png'];
$mime = (string)$doc['mime_type'];
if (!in_array($mime, $allowed, true)) { http_response_code(415); exit('Unsupported archived document format.'); }
$filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$doc['original_filename']) ?: 'score-sheet';
header('X-Content-Type-Options: nosniff');
header('Content-Type: '.$mime);
header('Content-Length: '.(string)filesize($fullPath));
header('Cache-Control: private, no-store, max-age=0');
header('Content-Disposition: '.($mode === 'download' ? 'attachment' : 'inline').'; filename="'.$filename.'"');
readfile($fullPath);
exit;
