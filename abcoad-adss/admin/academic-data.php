<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();

$modules = [
 'departments' => ['title'=>'Departments','table'=>'departments','fields'=>['department_name'=>['label'=>'Department name','max'=>150,'required'=>true],'department_code'=>['label'=>'Department code','max'=>30,'required'=>true],'description'=>['label'=>'Description','max'=>5000,'required'=>false]],'columns'=>['department_name'=>'Department','department_code'=>'Code']],
 'courses' => ['title'=>'Courses','table'=>'courses','fields'=>['department_id'=>['label'=>'Department','type'=>'department','required'=>true],'course_code'=>['label'=>'Course code','max'=>30,'required'=>true],'course_title'=>['label'=>'Course title','max'=>200,'required'=>true],'description'=>['label'=>'Description','max'=>5000,'required'=>false]],'columns'=>['course_code'=>'Code','course_title'=>'Course','department_name'=>'Department']],
 'sessions' => ['title'=>'Academic sessions','table'=>'academic_sessions','fields'=>['session_name'=>['label'=>'Session name','max'=>30,'required'=>true],'start_date'=>['label'=>'Start date','type'=>'date','required'=>false],'end_date'=>['label'=>'End date','type'=>'date','required'=>false]],'columns'=>['session_name'=>'Academic session','start_date'=>'Start date','end_date'=>'End date']],
 'semesters' => ['title'=>'Semesters','table'=>'semesters','fields'=>['semester_name'=>['label'=>'Semester','type'=>'semester','required'=>true],'description'=>['label'=>'Description','max'=>5000,'required'=>false]],'columns'=>['semester_name'=>'Semester','description'=>'Description']],
 'examination-types' => ['title'=>'Examination types','table'=>'examination_types','fields'=>['examination_name'=>['label'=>'Examination name','max'=>100,'required'=>true]],'columns'=>['examination_name'=>'Examination type']],
];
$activeModule = (string)($_GET['section'] ?? $_POST['section'] ?? 'departments');
if (!isset($modules[$activeModule])) $activeModule = 'departments';
$module = $modules[$activeModule];
$errors = [];

function adss_academic_record(PDO $pdo, array $module, int $id): ?array {
    $stmt = $pdo->prepare('SELECT * FROM ' . $module['table'] . ' WHERE id = :id LIMIT 1');
    $stmt->execute(['id'=>$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    try {
        if ($action === 'toggle') {
            if (!in_array($activeModule, ['departments','courses','sessions','semesters','examination-types'], true)) throw new RuntimeException('Invalid section.');
            $record = adss_academic_record($pdo, $module, $id);
            if (!$record) throw new RuntimeException('The selected record was not found.');
            $newStatus = (($record['status'] ?? 'active') === 'active') ? 'inactive' : 'active';
            $stmt = $pdo->prepare('UPDATE ' . $module['table'] . ' SET status = :status WHERE id = :id');
            $stmt->execute(['status'=>$newStatus,'id'=>$id]);
            log_activity($pdo, (int)$admin['id'], 'academic_record_status_changed', $module['table'], $id, $module['title'] . ' record status changed to ' . $newStatus . '.');
            flash('success', $module['title'] . ' record ' . $newStatus . '.');
            redirect('academic-data.php?section=' . rawurlencode($activeModule));
        }

        $values = [];
        foreach ($module['fields'] as $key=>$field) {
            $value = trim((string)($_POST[$key] ?? ''));
            if (($field['required'] ?? false) && $value === '') $errors[] = $field['label'] . ' is required.';
            if (($field['type'] ?? '') === 'semester') {
                if (!in_array($value, ['First Semester', 'Second Semester'], true)) $errors[] = 'Select First Semester or Second Semester.';
            } elseif (($field['type'] ?? '') === 'department') {
                $departmentId = filter_var($value, FILTER_VALIDATE_INT);
                if (!$departmentId) $errors[] = 'Select a valid department.';
                else {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = :id AND status = 'active'");
                    $check->execute(['id'=>$departmentId]);
                    if (!(int)$check->fetchColumn()) $errors[] = 'The selected department is not active.';
                    $value = (string)$departmentId;
                }
            } elseif (($field['type'] ?? '') === 'date' && $value !== '') {
                $date = DateTime::createFromFormat('Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value) $errors[] = 'Enter a valid ' . strtolower($field['label']) . '.';
            } elseif (isset($field['max']) && function_exists('mb_strlen') && mb_strlen($value) > $field['max']) {
                $errors[] = $field['label'] . ' exceeds the maximum length.';
            } elseif (isset($field['max']) && strlen($value) > $field['max'] * 4) {
                $errors[] = $field['label'] . ' is too long.';
            }
            $values[$key] = $value === '' ? null : $value;
        }
        if ($activeModule === 'sessions' && !empty($values['start_date']) && !empty($values['end_date']) && $values['start_date'] > $values['end_date']) $errors[] = 'The end date must be on or after the start date.';

        if (!$errors) {
            if ($id > 0) {
                $existing = adss_academic_record($pdo, $module, $id);
                if (!$existing) throw new RuntimeException('The record you are editing no longer exists.');
                $set = [];
                foreach (array_keys($values) as $key) $set[] = $key . ' = :' . $key;
                $sql = 'UPDATE ' . $module['table'] . ' SET ' . implode(', ', $set) . ' WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $values['id'] = $id;
                $stmt->execute($values);
                log_activity($pdo, (int)$admin['id'], 'academic_record_updated', $module['table'], $id, 'Updated ' . $module['title'] . ' record.');
                flash('success', $module['title'] . ' record updated successfully.');
            } else {
                $keys = array_keys($values);
                $sql = 'INSERT INTO ' . $module['table'] . ' (' . implode(', ', $keys) . ') VALUES (:' . implode(', :', $keys) . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                $newId = (int)$pdo->lastInsertId();
                log_activity($pdo, (int)$admin['id'], 'academic_record_created', $module['table'], $newId, 'Created ' . $module['title'] . ' record.');
                flash('success', $module['title'] . ' record added successfully.');
            }
            redirect('academic-data.php?section=' . rawurlencode($activeModule));
        }
    } catch (PDOException $exception) {
        error_log('ADSS academic data error: ' . $exception->getMessage());
        $errors[] = 'The record could not be saved. Check that its code or name is not already in use.';
    } catch (Throwable $exception) {
        if (!$errors) $errors[] = $exception->getMessage();
    }
}

$editId = filter_var($_GET['edit'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$editing = $editId ? adss_academic_record($pdo, $module, $editId) : null;
if ($editId && !$editing) $errors[] = 'The requested record could not be found.';
$departments = $pdo->query("SELECT id, department_name, department_code FROM departments WHERE status = 'active' ORDER BY department_name")->fetchAll();

if ($activeModule === 'courses') {
    $sql = 'SELECT c.*, d.department_name FROM courses c JOIN departments d ON d.id=c.department_id ORDER BY d.department_name, c.course_code';
} else {
    $sql = 'SELECT * FROM ' . $module['table'] . ' ORDER BY id DESC';
}
$records = $pdo->query($sql)->fetchAll();
$flashMessage = take_flash();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Academic Data Management | ABCOAD ADSS</title><link rel="stylesheet" href="../assets/css/styles.css"><link rel="stylesheet" href="../assets/css/auth.css"><style>
.acad-wrap{max-width:1180px;margin:28px auto;padding:0 22px}.acad-head{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}.acad-panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin:18px 0}.acad-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0}.acad-tabs a{padding:9px 13px;border:1px solid #dbe3ec;border-radius:8px;text-decoration:none;color:#334155;font-size:13px}.acad-tabs a.active{background:#0b1f44;color:#fff;border-color:#0b1f44}.acad-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.acad-field label{display:block;font-weight:700;font-size:13px;margin-bottom:6px}.acad-field input,.acad-field select,.acad-field textarea{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;background:#fff}.acad-field textarea{min-height:78px}.acad-field.full{grid-column:1/-1}.acad-alert{padding:11px 13px;border-radius:8px;margin:12px 0;background:#f1f5f9}.acad-alert.error{background:#fee2e2;color:#991b1b}.acad-alert.success{background:#dcfce7;color:#166534}.acad-table-wrap{overflow-x:auto}.acad-table{width:100%;border-collapse:collapse;font-size:13px}.acad-table th,.acad-table td{padding:11px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:middle}.acad-table th{background:#f8fafc}.acad-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.acad-actions a{color:#1d4ed8}.acad-actions button{border:0;background:none;color:#b45309;cursor:pointer;text-decoration:underline}.acad-status{font-size:11px;padding:4px 8px;border-radius:20px;background:#dcfce7;color:#166534}.acad-status.inactive{background:#f1f5f9;color:#64748b}.acad-submit{border:0;border-radius:8px;padding:11px 16px;background:#0b1f44;color:#fff;font-weight:700;cursor:pointer}.acad-muted{color:#64748b;font-size:12px}@media(max-width:650px){.acad-grid{grid-template-columns:1fr}.acad-field.full{grid-column:auto}.acad-wrap{padding:0 13px}}
</style></head><body class="app-page"><header class="app-header"><div class="app-brand"><img src="../assets/images/logo.png" alt=""><div><b>AUDU BAKO COLLEGE OF AGRICULTURE, DAMBATTA</b><small>Digital Examination Score Sheet Archive</small></div></div><div class="header-user"><span><?= e($admin['full_name']) ?> · Administrator</span><form method="post" action="../logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="logout-button" type="submit">Sign out</button></form></div></header>
<main class="acad-wrap"><div class="acad-head"><div><a href="index.php">← Administrator dashboard</a><h1>Academic Data Management</h1><p class="acad-muted">Maintain the reference data used by the score-sheet upload form. Records already referenced by archived documents should be deactivated, not deleted.</p></div></div>
<nav class="acad-tabs" aria-label="Academic data sections"><?php foreach ($modules as $key=>$item): ?><a class="<?= $activeModule===$key?'active':'' ?>" href="?section=<?= e($key) ?>"><?= e($item['title']) ?></a><?php endforeach; ?></nav>
<?php if ($flashMessage): ?><div class="acad-alert <?= e((string)$flashMessage['type']) ?>"><?= e((string)$flashMessage['message']) ?></div><?php endif; ?><?php foreach ($errors as $error): ?><div class="acad-alert error"><?= e($error) ?></div><?php endforeach; ?>
<section class="acad-panel"><h2><?= $editing ? 'Edit ' : 'Add ' ?><?= e(rtrim($module['title'],'s')) ?></h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="section" value="<?= e($activeModule) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : 0 ?>"><div class="acad-grid">
<?php foreach ($module['fields'] as $key=>$field): $value = $_POST && array_key_exists($key,$_POST) ? (string)$_POST[$key] : (string)($editing[$key] ?? ''); ?><div class="acad-field <?= ($key==='description'?'full':'') ?>"><label for="<?= e($key) ?>"><?= e($field['label']) ?><?= !empty($field['required'])?' *':'' ?></label><?php if (($field['type']??'')==='semester'): ?><select id="<?= e($key) ?>" name="<?= e($key) ?>" required><option value="">Select semester</option><?php foreach (['First Semester','Second Semester'] as $semesterOption): ?><option value="<?= e($semesterOption) ?>" <?= $value===$semesterOption?'selected':'' ?>><?= e($semesterOption) ?></option><?php endforeach; ?></select><?php elseif (($field['type']??'')==='department'): ?><select id="<?= e($key) ?>" name="<?= e($key) ?>" required><option value="">Select department</option><?php foreach ($departments as $department): ?><option value="<?= (int)$department['id'] ?>" <?= $value===(string)$department['id']?'selected':'' ?>><?= e($department['department_name'].' ('.$department['department_code'].')') ?></option><?php endforeach; ?></select><?php elseif (($field['type']??'')==='date'): ?><input type="date" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value) ?>"><?php elseif ($key==='description'): ?><textarea id="<?= e($key) ?>" name="<?= e($key) ?>" maxlength="5000"><?= e($value) ?></textarea><?php else: ?><input id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value) ?>" maxlength="<?= (int)($field['max']??200) ?>" <?= !empty($field['required'])?'required':'' ?>><?php endif; ?></div><?php endforeach; ?>
</div><p class="acad-actions"><button class="acad-submit" type="submit"><?= $editing?'Save changes':'Add record' ?></button><?php if ($editing): ?><a href="?section=<?= e($activeModule) ?>">Cancel editing</a><?php endif; ?></p></form></section>
<section class="acad-panel"><h2>Existing <?= e($module['title']) ?></h2><div class="acad-table-wrap"><table class="acad-table"><thead><tr><?php foreach ($module['columns'] as $key=>$label): ?><th><?= e($label) ?></th><?php endforeach; ?><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php if (!$records): ?><tr><td colspan="<?= count($module['columns'])+2 ?>" class="acad-muted">No records have been added yet.</td></tr><?php else: foreach ($records as $record): ?><tr><?php foreach ($module['columns'] as $key=>$label): ?><td><?= e((string)($record[$key] ?? '—') ?: '—') ?></td><?php endforeach; ?><td><span class="acad-status <?= ($record['status']??'active')==='inactive'?'inactive':'' ?>"><?= e(ucfirst((string)($record['status']??'active'))) ?></span></td><td><div class="acad-actions"><a href="?section=<?= e($activeModule) ?>&edit=<?= (int)$record['id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Change this record status?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="section" value="<?= e($activeModule) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$record['id'] ?>"><button type="submit"><?= ($record['status']??'active')==='active'?'Deactivate':'Activate' ?></button></form></div></td></tr><?php endforeach; endif; ?>
</tbody></table></div><p class="acad-muted">Deactivation keeps historical score-sheet references intact while removing the record from new upload selections.</p></section></main></body></html>
