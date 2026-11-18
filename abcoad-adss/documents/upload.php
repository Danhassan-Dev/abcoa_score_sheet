<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_staff_or_admin();
$isAdmin = ($user['role'] ?? '') === 'admin';
$basePath = '../';
$pageTitle = 'Upload Score Sheet';
$errors = [];
$successMessage = null;

function upload_response(bool $ok, string $message, int $status = 200): never
{
    $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    if ($isAjax) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_SLASHES);
        exit;
    }
    flash($ok ? 'success' : 'error', $message);
    redirect('upload.php');
}

function clean_original_filename(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? 'score-sheet';
    $name = trim($name);
    if ($name === '') $name = 'score-sheet';
    return mb_strcut($name, 0, 250, 'UTF-8');
}

$departmentStmt = $pdo->query("SELECT id, department_name, department_code FROM departments WHERE status = 'active' ORDER BY department_name");
$departments = $departmentStmt->fetchAll();

if ($isAdmin) {
    $courseStmt = $pdo->query(
        "SELECT id, department_id, course_code, course_title
         FROM courses WHERE status = 'active' ORDER BY course_code"
    );
} else {
    $courseStmt = $pdo->prepare(
        "SELECT id, department_id, course_code, course_title
         FROM courses WHERE status = 'active' AND department_id = :department_id ORDER BY course_code"
    );
    $courseStmt->execute(['department_id' => $user['department_id'] ?? 0]);
}
$courses = $courseStmt->fetchAll();

$sessions = $pdo->query("SELECT id, session_name FROM academic_sessions WHERE status = 'active' ORDER BY session_name DESC")->fetchAll();
$semesters = $pdo->query("SELECT id, semester_name FROM semesters ORDER BY id")->fetchAll();
$examTypes = $pdo->query("SELECT id, examination_name FROM examination_types WHERE status = 'active' ORDER BY examination_name")->fetchAll();

$settingStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = :setting_key LIMIT 1");
$settingStmt->execute(['setting_key' => 'max_upload_size_mb']);
$maxSizeMb = (int)($settingStmt->fetchColumn() ?: 10);
$maxSizeMb = max(1, min(100, $maxSizeMb));
$maxBytes = $maxSizeMb * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $departmentId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT) ?: 0;
    $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT) ?: 0;
    $sessionId = filter_input(INPUT_POST, 'academic_session_id', FILTER_VALIDATE_INT) ?: 0;
    $semesterId = filter_input(INPUT_POST, 'semester_id', FILTER_VALIDATE_INT) ?: 0;
    $examTypeRaw = trim((string)($_POST['examination_type_id'] ?? ''));
    $examTypeId = $examTypeRaw === '' ? null : (ctype_digit($examTypeRaw) ? (int)$examTypeRaw : -1);
    $lecturerName = trim((string)($_POST['lecturer_name'] ?? ''));
    $referenceNumber = trim((string)($_POST['reference_number'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));

    if (!$departmentId || !$courseId || !$sessionId || !$semesterId) $errors[] = 'Select the department, course, academic session and semester.';
    if ($lecturerName === '' || mb_strlen($lecturerName) > 150) $errors[] = 'Enter the lecturer name (maximum 150 characters).';
    if (mb_strlen($referenceNumber) > 100) $errors[] = 'The reference number is too long.';
    if (mb_strlen($description) > 5000) $errors[] = 'The description must not exceed 5,000 characters.';
    if ($examTypeId === -1) $errors[] = 'Select a valid examination type.';

    if (!$isAdmin && (int)($user['department_id'] ?? 0) !== $departmentId) {
        $errors[] = 'You can only upload score sheets for your assigned department.';
    }

    $departmentCheck = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = :id AND status = 'active'");
    $departmentCheck->execute(['id' => $departmentId]);
    if (!$departmentCheck->fetchColumn()) $errors[] = 'The selected department is not active or does not exist.';

    $courseCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM courses WHERE id = :course_id AND department_id = :department_id AND status = 'active'"
    );
    $courseCheck->execute(['course_id' => $courseId, 'department_id' => $departmentId]);
    if (!$courseCheck->fetchColumn()) $errors[] = 'The selected course does not belong to the selected active department.';

    $sessionCheck = $pdo->prepare("SELECT COUNT(*) FROM academic_sessions WHERE id = :id AND status = 'active'");
    $sessionCheck->execute(['id' => $sessionId]);
    if (!$sessionCheck->fetchColumn()) $errors[] = 'Select an active academic session.';

    $semesterCheck = $pdo->prepare("SELECT COUNT(*) FROM semesters WHERE id = :id");
    $semesterCheck->execute(['id' => $semesterId]);
    if (!$semesterCheck->fetchColumn()) $errors[] = 'Select a valid semester.';

    if ($examTypeId !== null && $examTypeId > 0) {
        $examCheck = $pdo->prepare("SELECT COUNT(*) FROM examination_types WHERE id = :id AND status = 'active'");
        $examCheck->execute(['id' => $examTypeId]);
        if (!$examCheck->fetchColumn()) $errors[] = 'Select a valid active examination type.';
    }

    if (!isset($_FILES['score_sheet']) || !is_array($_FILES['score_sheet'])) {
        $errors[] = 'Choose a JPG, PNG or PDF score sheet to upload.';
    } else {
        $file = $_FILES['score_sheet'];
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'The file exceeds the server upload_max_filesize limit.',
                UPLOAD_ERR_FORM_SIZE => 'The file exceeds the permitted upload size.',
                UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
                UPLOAD_ERR_NO_FILE => 'Choose a score sheet file.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server temporary upload directory is missing.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
                UPLOAD_ERR_EXTENSION => 'A server extension stopped the upload.',
            ];
            $errors[] = $uploadErrors[(int)$file['error']] ?? 'The file upload failed.';
        } elseif (!is_uploaded_file((string)$file['tmp_name'])) {
            $errors[] = 'The uploaded file could not be validated.';
        } elseif ((int)$file['size'] < 1 || (int)$file['size'] > $maxBytes) {
            $errors[] = 'The file must be greater than 0 bytes and no larger than ' . $maxSizeMb . ' MB.';
        }
    }

    if (!$errors && isset($file)) {
        $originalName = clean_original_filename((string)$file['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedMimeByExtension = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file((string)$file['tmp_name']) ?: '';
        if (!isset($allowedMimeByExtension[$extension])) {
            $errors[] = 'Unsupported file extension. Allowed formats: JPG, JPEG, PNG and PDF.';
        } elseif ($detectedMime !== $allowedMimeByExtension[$extension]) {
            $errors[] = 'The file content does not match its extension. Please select a genuine JPG, PNG or PDF file.';
        } elseif (str_starts_with($detectedMime, 'image/')) {
            $imageInfo = @getimagesize((string)$file['tmp_name']);
            if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== $detectedMime) {
                $errors[] = 'The uploaded image is invalid or cannot be decoded.';
            }
        }
    }

    if (!$errors && isset($file)) {
        $relativeDirectory = date('Y') . '/' . date('m');
        $storageRoot = adss_storage_root();
        $absoluteDirectory = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0750, true) && !is_dir($absoluteDirectory)) {
            $errors[] = 'The document storage directory could not be created. Check folder permissions.';
        } elseif (!is_writable($absoluteDirectory)) {
            $errors[] = 'The document storage directory is not writable. Check folder permissions.';
        } else {
            $storedFilename = bin2hex(random_bytes(24)) . '.' . $extension;
            $relativePath = $relativeDirectory . '/' . $storedFilename;
            $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $storedFilename;
            $fileHash = hash_file('sha256', (string)$file['tmp_name']);

            if ($fileHash === false || !move_uploaded_file((string)$file['tmp_name'], $absolutePath)) {
                $errors[] = 'The server could not save the uploaded document. Please try again.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $insert = $pdo->prepare(
                        "INSERT INTO score_sheets
                         (course_id, academic_session_id, semester_id, examination_type_id, uploaded_by,
                          lecturer_name, reference_number, description, original_filename, stored_filename,
                          file_path, mime_type, file_size, file_hash, verification_status)
                         VALUES
                         (:course_id, :session_id, :semester_id, :exam_type_id, :uploaded_by,
                          :lecturer_name, :reference_number, :description, :original_filename, :stored_filename,
                          :file_path, :mime_type, :file_size, :file_hash, 'pending')"
                    );
                    $insert->execute([
                        'course_id' => $courseId,
                        'session_id' => $sessionId,
                        'semester_id' => $semesterId,
                        'exam_type_id' => $examTypeId,
                        'uploaded_by' => (int)$user['id'],
                        'lecturer_name' => $lecturerName,
                        'reference_number' => $referenceNumber !== '' ? $referenceNumber : null,
                        'description' => $description !== '' ? $description : null,
                        'original_filename' => $originalName,
                        'stored_filename' => $storedFilename,
                        'file_path' => $relativePath,
                        'mime_type' => $detectedMime,
                        'file_size' => (int)$file['size'],
                        'file_hash' => $fileHash,
                    ]);
                    $scoreSheetId = (int)$pdo->lastInsertId();
                    log_activity(
                        $pdo,
                        (int)$user['id'],
                        'score_sheet_uploaded',
                        'score_sheet',
                        $scoreSheetId,
                        'Uploaded ' . $originalName . ' (' . strtoupper($extension) . ')'
                    );
                    $pdo->commit();
                    upload_response(true, 'Score sheet uploaded successfully and submitted with Pending verification status.');
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    if (is_file($absolutePath)) @unlink($absolutePath);
                    error_log('ADSS score-sheet upload failed: ' . $exception->getMessage());
                    upload_response(false, 'The score sheet could not be recorded. No archive entry was created. Please try again.', 500);
                }
            }
        }
    }

    if ($errors) {
        upload_response(false, implode(' ', $errors), 422);
    }
}

$flashMessage = take_flash();
$initials = '';
foreach (preg_split('/\s+/', trim((string)$user['full_name'])) ?: [] as $part) {
    if ($part !== '') $initials .= strtoupper(substr($part, 0, 1));
    if (strlen($initials) >= 2) break;
}
$initials = $initials ?: 'U';
$staffDepartmentId = (int)($user['department_id'] ?? 0);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Upload Score Sheet | ABCOAD ADSS</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="stylesheet" href="../assets/css/auth.css">
  <style>
    .nav-link{text-decoration:none}.nav-coming{margin-left:auto;font-size:8px;color:#9fb0c0}
    .dashboard-user-form{margin:0}.dashboard-user-form button{border:0;background:transparent;color:#dce6ef;padding:9px 10px;font:600 10px Inter,Arial,sans-serif;cursor:pointer}
    .upload-page{max-width:1120px;margin:0 auto}.upload-intro{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:18px}
    .upload-intro h2{font-size:21px;color:#203b55;margin:0 0 7px}.upload-intro p{font-size:11px;color:#7b8b9a;line-height:1.6;margin:0;max-width:680px}
    .upload-layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(250px,.8fr);gap:17px;align-items:start}
    .upload-panel{background:#fff;border:1px solid #e6ebf0;border-radius:12px;padding:21px;box-shadow:0 5px 18px rgba(20,43,65,.035)}
    .upload-panel h3{font-size:13px;color:#263f57;margin:0 0 5px}.upload-panel .sub{font-size:10px;color:#91a0ad;margin:0 0 18px}
    .upload-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .field{min-width:0}.field.full{grid-column:1/-1}.field label{display:block;font-size:10px;font-weight:700;color:#52677a;margin-bottom:7px}
    .field input,.field select,.field textarea{width:100%;min-height:39px;border:1px solid #dfe6ed;border-radius:7px;padding:9px 11px;font:11px Inter,Arial,sans-serif;color:#34495e;background:#fff;outline:none}
    .field textarea{min-height:88px;resize:vertical}.field input:focus,.field select:focus,.field textarea:focus{border-color:#4a8bc4;box-shadow:0 0 0 3px rgba(74,139,196,.1)}
    .field .hint{display:block;font-size:9px;color:#9aa7b2;margin-top:5px;line-height:1.45}
    .drop-zone{border:1.5px dashed #bdcbd8;background:#f8fafc;border-radius:10px;padding:22px 16px;text-align:center;cursor:pointer;transition:.15s}
    .drop-zone.dragover{border-color:#3685c4;background:#edf6ff}.drop-zone .upload-symbol{font-size:25px;color:#397fb7;margin-bottom:7px}
    .drop-zone strong{display:block;font-size:11px;color:#304b64}.drop-zone p{font-size:9px;color:#8c9aa7;margin:6px 0 0}
    .file-preview{display:none;margin-top:12px;padding:10px;border:1px solid #e3e9ef;border-radius:8px;align-items:center;gap:11px}
    .file-preview.show{display:flex}.file-preview img{width:54px;height:54px;object-fit:contain;border-radius:5px;background:#f3f6f9}.file-preview .file-meta{min-width:0;flex:1}
    .file-preview b{display:block;font-size:10px;overflow-wrap:anywhere;color:#354c61}.file-preview small{font-size:9px;color:#8b9aa8}
    .remove-file{border:0;background:#fff0ef;color:#c14d48;border-radius:6px;padding:7px 9px;font-size:9px;cursor:pointer}
    .upload-progress{display:none;margin-top:12px}.upload-progress.show{display:block}.progress-track{height:7px;border-radius:20px;background:#e9eef3;overflow:hidden}.progress-fill{height:100%;width:0;background:#2785bd;transition:width .1s}.progress-label{font-size:9px;color:#718497;margin-top:6px;display:flex;justify-content:space-between}
    .upload-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:19px}.upload-actions .btn{border:0;cursor:pointer}
    .upload-guidance{display:grid;gap:12px}.guidance-item{display:flex;gap:10px;align-items:flex-start}.guidance-icon{width:29px;height:29px;border-radius:8px;display:grid;place-items:center;background:#edf4fa;color:#3979ae;flex:none;font-size:14px}
    .guidance-item b{display:block;font-size:10px;color:#3b5268}.guidance-item p{font-size:9px;line-height:1.5;color:#8b99a6;margin:4px 0 0}
    .notice{margin-bottom:15px}.server-errors{display:none;background:#fff1ef;color:#b94c45;border:1px solid #f4d2ce;border-radius:8px;padding:11px 13px;font-size:10px;line-height:1.6;margin-bottom:14px}
    .server-errors.show{display:block}.upload-success{display:none;background:#eaf7ef;color:#18734d;border:1px solid #cdebd9;border-radius:8px;padding:11px 13px;font-size:10px;line-height:1.6;margin-bottom:14px}
    .upload-success.show{display:block}
    @media(max-width:980px){.upload-layout{grid-template-columns:1fr}.upload-guidance{grid-template-columns:1fr 1fr}}
    @media(max-width:760px){.upload-intro{flex-direction:column}.upload-form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.upload-panel{padding:16px}.upload-guidance{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <img src="../assets/images/logo.png" alt="Audu Bako College of Agriculture logo" class="brand-logo">
      <div class="brand-name">AUDU BAKO COLLEGE<br>OF AGRICULTURE, DAMBATTA</div>
      <div class="brand-tag">DIGITAL SCORE SHEET ARCHIVE</div>
    </div>
    <div class="nav-label">WORKSPACE</div>
    <nav class="nav-menu" aria-label="Main navigation">
      <a class="nav-link" href="<?= $isAdmin ? '../admin/index.php' : '../staff/index.php' ?>"><span class="nav-icon">▦</span>Dashboard</a>
      <a class="nav-link active" href="upload.php"><span class="nav-icon">⇧</span>Upload Score Sheet</a>
      <a class="nav-link" href="archive.php"><span class="nav-icon">▤</span>Document Archive</a>
      <button class="nav-link" type="button" disabled><span class="nav-icon">✓</span>Verification Centre <span class="nav-coming">PHASE 7</span></button>
      <?php if ($isAdmin): ?><a class="nav-link" href="../admin/users.php"><span class="nav-icon">♙</span>Staff Management</a><?php endif; ?>
      <button class="nav-link" type="button" disabled><span class="nav-icon">◷</span>Activity Logs <span class="nav-coming">PHASE 8</span></button>
    </nav>
    <div class="sidebar-bottom">
      <button class="nav-link" type="button" disabled><span class="nav-icon">⚙</span>Settings <span class="nav-coming">PHASE 8</span></button>
      <div class="sidebar-user"><div class="avatar"><?= e($initials) ?></div><div><b><?= e($user['full_name']) ?></b><small><?= $isAdmin ? 'Administrator' : 'Staff member' ?></small></div></div>
      <form class="dashboard-user-form" method="post" action="../logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button type="submit">↪ Sign out</button></form>
    </div>
  </aside>
  <main class="main-area">
    <header class="topbar">
      <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false">☰</button>
      <div class="topbar-title"><span class="eyebrow">AUDU BAKO COLLEGE OF AGRICULTURE, DAMBATTA</span><h1>Upload Score Sheet</h1></div>
      <div class="topbar-actions"><div class="top-profile"><div class="avatar"><?= e($initials) ?></div><div><b><?= e($user['full_name']) ?></b><small><?= $isAdmin ? 'Administrator' : 'Staff member' ?></small></div></div></div>
    </header>
    <div class="content">
      <?php if ($flashMessage): ?><div class="notice <?= e($flashMessage['type']) ?>"><span>ⓘ</span><div><?= e($flashMessage['message']) ?></div></div><?php endif; ?>
      <section class="upload-page">
        <div class="upload-intro"><div><h2>Archive an examination score sheet</h2><p>Provide the document details and upload the original scanned score sheet. Every new upload enters the archive with Pending verification status.</p></div></div>
        <div id="uploadErrors" class="server-errors" role="alert"></div>
        <div id="uploadSuccess" class="upload-success" role="status"></div>
        <div class="upload-layout">
          <section class="upload-panel">
            <h3>Document information</h3><p class="sub">Fields marked with * are required.</p>
            <form id="scoreSheetForm" method="post" enctype="multipart/form-data" novalidate>
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <div class="upload-form-grid">
                <div class="field">
                  <label for="department_id">Department *</label>
                  <select id="department_id" name="department_id" required <?= !$isAdmin ? 'disabled' : '' ?>>
                    <option value="">Select department</option>
                    <?php foreach ($departments as $department): ?>
                      <option value="<?= (int)$department['id'] ?>" <?= (!$isAdmin && $staffDepartmentId === (int)$department['id']) ? 'selected' : '' ?>><?= e($department['department_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (!$isAdmin): ?><input type="hidden" name="department_id" value="<?= $staffDepartmentId ?>"><?php endif; ?>
                </div>
                <div class="field">
                  <label for="course_id">Course *</label>
                  <select id="course_id" name="course_id" required>
                    <option value="">Select course</option>
                    <?php foreach ($courses as $course): ?>
                      <option value="<?= (int)$course['id'] ?>" data-department="<?= (int)$course['department_id'] ?>"><?= e($course['course_code'] . ' — ' . $course['course_title']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="academic_session_id">Academic session *</label>
                  <select id="academic_session_id" name="academic_session_id" required><option value="">Select academic session</option>
                    <?php foreach ($sessions as $session): ?><option value="<?= (int)$session['id'] ?>"><?= e($session['session_name']) ?></option><?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="semester_id">Semester *</label>
                  <select id="semester_id" name="semester_id" required><option value="">Select semester</option>
                    <?php foreach ($semesters as $semester): ?><option value="<?= (int)$semester['id'] ?>"><?= e($semester['semester_name']) ?></option><?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="examination_type_id">Examination type</label>
                  <select id="examination_type_id" name="examination_type_id"><option value="">Not specified</option>
                    <?php foreach ($examTypes as $examType): ?><option value="<?= (int)$examType['id'] ?>"><?= e($examType['examination_name']) ?></option><?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="lecturer_name">Lecturer name *</label>
                  <input id="lecturer_name" name="lecturer_name" type="text" maxlength="150" required placeholder="Enter lecturer's full name">
                </div>
                <div class="field">
                  <label for="reference_number">Document reference number</label>
                  <input id="reference_number" name="reference_number" type="text" maxlength="100" placeholder="Optional reference number">
                </div>
                <div class="field full">
                  <label for="description">Description / notes</label>
                  <textarea id="description" name="description" maxlength="5000" placeholder="Optional notes about this archived score sheet"></textarea>
                </div>
                <div class="field full">
                  <label for="score_sheet">Score sheet file *</label>
                  <div class="drop-zone" id="dropZone" tabindex="0" role="button" aria-label="Choose or drop a score sheet file">
                    <div class="upload-symbol">⇧</div><strong>Drag and drop your file here</strong><p>or click to browse · JPG, JPEG, PNG or PDF · Maximum <?= $maxSizeMb ?> MB</p>
                  </div>
                  <input id="score_sheet" name="score_sheet" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" required hidden>
                  <div class="file-preview" id="filePreview"><img id="previewImage" alt="Selected file preview"><div class="file-meta"><b id="previewName"></b><small id="previewSize"></small></div><button class="remove-file" type="button" id="removeFile">Remove</button></div>
                  <span class="hint">The original file is stored without image editing, compression or conversion.</span>
                  <div class="upload-progress" id="uploadProgress"><div class="progress-track"><div class="progress-fill" id="progressFill"></div></div><div class="progress-label"><span id="progressText">Uploading…</span><span id="progressPercent">0%</span></div></div>
                </div>
              </div>
              <div class="upload-actions"><button class="btn btn-primary" type="submit" id="submitUpload">Submit score sheet <span>→</span></button></div>
            </form>
          </section>
          <aside class="upload-panel">
            <h3>Upload guidelines</h3><p class="sub">Important information before submitting.</p>
            <div class="upload-guidance">
              <div class="guidance-item"><div class="guidance-icon">▣</div><div><b>Supported file formats</b><p>JPG, JPEG, PNG and PDF documents are accepted. Other file types are rejected.</p></div></div>
              <div class="guidance-item"><div class="guidance-icon">⇧</div><div><b>Maximum file size</b><p>Each upload must be no larger than <?= $maxSizeMb ?> MB. The server also enforces its PHP upload limits.</p></div></div>
              <div class="guidance-item"><div class="guidance-icon">🔒</div><div><b>Protected storage</b><p>Documents are stored in a protected server directory and are not exposed through direct public links.</p></div></div>
              <div class="guidance-item"><div class="guidance-icon">✓</div><div><b>Verification workflow</b><p>Every new score sheet is marked Pending until an authorized administrator reviews it.</p></div></div>
              <div class="guidance-item"><div class="guidance-icon">⛨</div><div><b>Original document preservation</b><p>The uploaded file is stored as received. The system does not calculate, edit or alter examination scores.</p></div></div>
            </div>
          </aside>
        </div>
      </section>
    </div>
  </main>
</div>
<script src="../assets/js/app.js"></script>
<script>
(() => {
  const form = document.getElementById('scoreSheetForm');
  const fileInput = document.getElementById('score_sheet');
  const dropZone = document.getElementById('dropZone');
  const preview = document.getElementById('filePreview');
  const previewImage = document.getElementById('previewImage');
  const previewName = document.getElementById('previewName');
  const previewSize = document.getElementById('previewSize');
  const errors = document.getElementById('uploadErrors');
  const success = document.getElementById('uploadSuccess');
  const progress = document.getElementById('uploadProgress');
  const progressFill = document.getElementById('progressFill');
  const progressText = document.getElementById('progressText');
  const progressPercent = document.getElementById('progressPercent');
  const submit = document.getElementById('submitUpload');
  const department = document.getElementById('department_id');
  const course = document.getElementById('course_id');
  const maxBytes = <?= (int)$maxBytes ?>;
  const maxSizeMb = <?= (int)$maxSizeMb ?>;
  let objectUrl = null;

  const formatSize = bytes => bytes < 1024 * 1024 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
  function clearMessages() { errors.classList.remove('show'); errors.textContent = ''; success.classList.remove('show'); success.textContent = ''; }
  function clearFile() {
    fileInput.value = '';
    preview.classList.remove('show');
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = null;
    previewImage.removeAttribute('src');
  }
  function setFile(file) {
    clearMessages();
    if (!file) return;
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['jpg','jpeg','png','pdf'].includes(ext)) { errors.textContent = 'Unsupported file. Select JPG, JPEG, PNG or PDF.'; errors.classList.add('show'); clearFile(); return; }
    if (file.size < 1 || file.size > maxBytes) { errors.textContent = `The selected file must be no larger than ${maxSizeMb} MB.`; errors.classList.add('show'); clearFile(); return; }
    previewName.textContent = file.name;
    previewSize.textContent = `${formatSize(file.size)} · ${ext.toUpperCase()}`;
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    if (file.type.startsWith('image/')) {
      objectUrl = URL.createObjectURL(file);
      previewImage.src = objectUrl;
      previewImage.style.display = 'block';
    } else {
      previewImage.src = '../assets/images/logo.png';
      previewImage.style.display = 'block';
    }
    preview.classList.add('show');
  }
  dropZone.addEventListener('click', () => fileInput.click());
  dropZone.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); fileInput.click(); } });
  fileInput.addEventListener('change', () => setFile(fileInput.files[0]));
  document.getElementById('removeFile').addEventListener('click', clearFile);
  ['dragenter','dragover'].forEach(name => dropZone.addEventListener(name, event => { event.preventDefault(); dropZone.classList.add('dragover'); }));
  ['dragleave','drop'].forEach(name => dropZone.addEventListener(name, event => { event.preventDefault(); dropZone.classList.remove('dragover'); }));
  dropZone.addEventListener('drop', event => {
    const file = event.dataTransfer.files[0];
    if (!file) return;
    const transfer = new DataTransfer(); transfer.items.add(file); fileInput.files = transfer.files; setFile(file);
  });
  if (department && !department.disabled) {
    department.addEventListener('change', () => {
      const selected = department.value;
      [...course.options].forEach((option, index) => {
        if (index === 0) { option.hidden = false; return; }
        option.hidden = option.dataset.department !== selected;
      });
      course.value = '';
    });
  }
  form.addEventListener('submit', event => {
    event.preventDefault();
    clearMessages();
    if (!form.reportValidity()) return;
    if (!fileInput.files.length) { errors.textContent = 'Choose a score sheet file before submitting.'; errors.classList.add('show'); return; }
    submit.disabled = true; submit.textContent = 'Uploading…';
    progress.classList.add('show'); progressFill.style.width = '0%'; progressPercent.textContent = '0%'; progressText.textContent = 'Uploading file…';
    const xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.upload.addEventListener('progress', event => {
      if (event.lengthComputable) {
        const percent = Math.round((event.loaded / event.total) * 100);
        progressFill.style.width = `${percent}%`; progressPercent.textContent = `${percent}%`;
      }
    });
    xhr.addEventListener('load', () => {
      let result;
      try { result = JSON.parse(xhr.responseText); } catch (_) { result = {ok:false,message:'The server returned an unexpected response. Check the PHP error log.'}; }
      if (xhr.status >= 200 && xhr.status < 300 && result.ok) {
        progressFill.style.width = '100%'; progressPercent.textContent = '100%'; progressText.textContent = 'Upload complete';
        success.textContent = result.message; success.classList.add('show');
        form.reset(); clearFile();
        if (department && department.disabled) {
          const hiddenDepartment = form.querySelector('input[name="department_id"]');
          if (hiddenDepartment) hiddenDepartment.value = <?= $staffDepartmentId ?>;
        }
      } else {
        errors.textContent = result.message || 'Upload failed. Please try again.'; errors.classList.add('show');
        progress.classList.remove('show');
      }
      submit.disabled = false; submit.innerHTML = 'Submit score sheet <span>→</span>';
    });
    xhr.addEventListener('error', () => {
      errors.textContent = 'A network error interrupted the upload. Please try again.'; errors.classList.add('show');
      progress.classList.remove('show'); submit.disabled = false; submit.innerHTML = 'Submit score sheet <span>→</span>';
    });
    xhr.send(new FormData(form));
  });
})();
</script>
</body>
</html>
