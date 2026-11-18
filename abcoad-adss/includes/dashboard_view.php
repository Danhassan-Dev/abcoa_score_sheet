<?php
/** Shared dashboard interface for administrator and staff roles. */
$roleLabel = $isAdmin ? 'Administrator' : 'Staff member';
$firstName = trim((string)$user['full_name']);
$initials = '';
foreach (preg_split('/\s+/', $firstName) ?: [] as $part) {
    if ($part !== '') {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    if (strlen($initials) >= 2) break;
}
$initials = $initials !== '' ? $initials : 'U';
$formatDate = static function (?string $date): string {
    if (!$date) return '—';
    $timestamp = strtotime($date);
    return $timestamp === false ? '—' : date('d M Y · h:i A', $timestamp);
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($roleLabel) ?> Dashboard | ABCOAD ADSS</title>
  <link rel="stylesheet" href="<?= e($basePath) ?>assets/css/styles.css">
  <link rel="stylesheet" href="<?= e($basePath) ?>assets/css/auth.css">
  <style>
    .nav-link{text-decoration:none}
    .quick-action{text-decoration:none;color:inherit}
    .nav-link[disabled]{opacity:.48;cursor:not-allowed}
    .nav-link[disabled]:hover{background:transparent;color:#dce6ef}
    .nav-coming{margin-left:auto;font-size:8px;color:#9fb0c0;letter-spacing:.2px}
    .dashboard-section{margin-top:17px}
    .dashboard-section .panel-heading{padding:18px 19px 12px}
    .recent-activity{padding:0 19px 12px}
    .activity-item{display:flex;align-items:flex-start;gap:12px;padding:13px 0;border-bottom:1px solid var(--line)}
    .activity-item:last-child{border-bottom:0}
    .activity-dot{width:30px;height:30px;flex:none;border-radius:50%;background:#eaf2fb;color:#3979b9;display:grid;place-items:center}
    .activity-copy{min-width:0;flex:1}
    .activity-copy b{display:block;font-size:10px;color:#30465b;overflow-wrap:anywhere}
    .activity-copy p{font-size:10px;color:#788999;margin:4px 0;line-height:1.5;overflow-wrap:anywhere}
    .activity-copy small{font-size:9px;color:#a0abb6}
    .empty-dashboard{padding:25px;text-align:center;color:#8795a3;font-size:11px}
    .status.pending{background:#fff4dc;color:#9b6b0b}.status.verified{background:#e5f5ec;color:#168255}.status.rejected{background:#fde9e8;color:#c44c48}
    .welcome-copy h2{overflow-wrap:anywhere}
    .dashboard-user-form{margin:0}
    .dashboard-user-form button{border:0;background:transparent;color:#dce6ef;padding:9px 10px;font:600 10px Inter,Arial,sans-serif;cursor:pointer}
    .dashboard-user-form button:hover{color:#ffd56d}
    .text-button.disabled-action{color:#9aa6b2;cursor:not-allowed}
    .stat-card strong{font-variant-numeric:tabular-nums}
    @media(max-width:760px){.recent-activity{padding:0 14px 10px}}
  </style>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <img src="<?= e($basePath) ?>assets/images/logo.png" alt="Audu Bako College of Agriculture logo" class="brand-logo">
      <div class="brand-name">AUDU BAKO COLLEGE<br>OF AGRICULTURE, DAMBATTA</div>
      <div class="brand-tag">DIGITAL SCORE SHEET ARCHIVE</div>
    </div>
    <div class="nav-label">WORKSPACE</div>
    <nav class="nav-menu" aria-label="Main navigation">
      <a class="nav-link active" href="<?= e($isAdmin ? 'index.php' : '../staff/index.php') ?>"><span class="nav-icon">▦</span>Dashboard</a>
      <a class="nav-link" href="../documents/upload.php"><span class="nav-icon">⇧</span>Upload Score Sheet</a>
      <a class="nav-link" href="../documents/archive.php"><span class="nav-icon">▤</span>Document Archive</a>
      <?php if ($isAdmin): ?><a class="nav-link" href="verification.php"><span class="nav-icon">✓</span>Verification Centre <span class="nav-count"><?= $dashboard['pending_documents'] ?></span></a><?php else: ?><button class="nav-link" type="button" disabled title="Administrator access only"><span class="nav-icon">✓</span>Verification Centre</button><?php endif; ?>
      <?php if ($isAdmin): ?>
        <a class="nav-link" href="users.php"><span class="nav-icon">♙</span>Staff Management</a>
      <?php else: ?>
        <button class="nav-link" type="button" disabled title="Administrator access only"><span class="nav-icon">♙</span>Staff Management</button>
      <?php endif; ?>
      <?php if ($isAdmin): ?><a class="nav-link" href="activity-logs.php"><span class="nav-icon">◷</span>Activity Logs</a><?php endif; ?>
    </nav>
    <div class="sidebar-bottom">
      <?php if ($isAdmin): ?><a class="nav-link" href="settings.php"><span class="nav-icon">⚙</span>Settings</a><?php endif; ?>
      <div class="sidebar-user">
        <div class="avatar"><?= e($initials) ?></div>
        <div><b><?= e($user['full_name']) ?></b><small><?= e($roleLabel) ?></small></div>
      </div>
      <form class="dashboard-user-form" method="post" action="<?= e($basePath) ?>logout.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit">↪ Sign out</button>
      </form>
    </div>
  </aside>

  <main class="main-area">
    <header class="topbar">
      <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false">☰</button>
      <div class="topbar-title">
        <span class="eyebrow">AUDU BAKO COLLEGE OF AGRICULTURE, DAMBATTA</span>
        <h1>Dashboard</h1>
      </div>
      <div class="topbar-actions">
        <div class="top-profile"><div class="avatar"><?= e($initials) ?></div><div><b><?= e($user['full_name']) ?></b><small><?= e($roleLabel) ?></small></div></div>
      </div>
    </header>

    <div class="content">
      <?php if ($flashMessage): ?>
        <div class="notice <?= e($flashMessage['type']) ?>"><span>ⓘ</span><div><?= e($flashMessage['message']) ?></div></div>
      <?php endif; ?>
      <section class="page active" aria-labelledby="dashboardHeading">
        <div class="welcome-banner">
          <div class="welcome-copy">
            <span class="welcome-kicker">DIGITAL RECORDS, SAFELY PRESERVED</span>
            <h2 id="dashboardHeading">Welcome back, <?= e($user['full_name']) ?></h2>
            <p><?= $isAdmin ? 'Manage, preserve and retrieve examination score sheets in one secure archive.' : 'View your submitted examination records and monitor their archive status.' ?></p>
            <a class="btn btn-gold" href="../documents/upload.php">＋ Upload score sheet <span>→</span></a>
          </div>
          <div class="banner-mark"><img src="<?= e($basePath) ?>assets/images/logo.png" alt=""></div>
        </div>

        <div class="stats-grid" aria-label="Archive statistics">
          <article class="stat-card"><div class="stat-icon blue">▤</div><div><span><?= $isAdmin ? 'Total documents' : 'My documents' ?></span><strong><?= number_format($dashboard['total_documents']) ?></strong><small><?= $isAdmin ? 'All archived records' : 'Records uploaded by you' ?></small></div></article>
          <article class="stat-card"><div class="stat-icon green">✓</div><div><span>Verified documents</span><strong><?= number_format($dashboard['verified_documents']) ?></strong><small>Verified archive records</small></div></article>
          <article class="stat-card"><div class="stat-icon amber">◷</div><div><span>Pending review</span><strong><?= number_format($dashboard['pending_documents']) ?></strong><small>Awaiting verification</small></div></article>
          <article class="stat-card"><div class="stat-icon violet">▦</div><div><span>Departments</span><strong><?= number_format($dashboard['department_count']) ?></strong><small>Active departments</small></div></article>
        </div>

        <div class="dashboard-grid">
          <article class="panel recent-panel">
            <div class="panel-heading"><div><h3><?= $isAdmin ? 'Recent uploads' : 'My recent uploads' ?></h3><p><?= $isAdmin ? 'Latest examination records added to the archive' : 'Latest examination records you submitted' ?></p></div><a class="text-button" href="../documents/archive.php">Open archive →</a></div>
            <div class="table-wrap">
              <table>
                <thead><tr><th>DOCUMENT</th><th>COURSE</th><th>SESSION</th><th>UPLOADED BY</th><th>STATUS</th></tr></thead>
                <tbody>
                <?php if (!$dashboard['recent_uploads']): ?>
                  <tr><td colspan="5" class="empty-dashboard">No score sheets have been uploaded yet.</td></tr>
                <?php else: foreach ($dashboard['recent_uploads'] as $record): ?>
                  <tr>
                    <td><div class="doc-cell"><span class="doc-mini">▤</span><span><b><?= e($record['original_filename']) ?></b><small><?= e($formatDate($record['created_at'])) ?></small></span></div></td>
                    <td><b><?= e($record['course_code']) ?></b><br><small><?= e($record['course_title']) ?></small></td>
                    <td><?= e($record['session_name']) ?></td>
                    <td><?= e($record['uploader_name']) ?></td>
                    <td><span class="status <?= e($record['verification_status']) ?>"><?= e(ucfirst($record['verification_status'])) ?></span></td>
                  </tr>
                <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </article>

          <article class="panel quick-panel">
            <div class="panel-heading"><div><h3>Quick actions</h3><p>Common tasks</p></div></div>
            <?php if ($isAdmin): ?>
              <a class="quick-action" href="users.php"><span class="quick-icon blue">♙</span><span><b>Manage staff</b><small>Create accounts and manage access</small></span><i>›</i></a>
            <?php endif; ?>
            <a class="quick-action" href="../documents/upload.php"><span class="quick-icon blue">⇧</span><span><b>Upload score sheet</b><small>Archive a scanned examination record</small></span><i>›</i></a>
            <a class="quick-action" href="../documents/archive.php"><span class="quick-icon green">⌕</span><span><b>Search archive</b><small>Find archived examination records</small></span><i>›</i></a>
            <?php if ($isAdmin): ?>
              <a class="quick-action" href="verification.php"><span class="quick-icon amber">✓</span><span><b>Review submissions</b><small>Open the verification centre</small></span><i>›</i></a>
            <?php endif; ?>
          </article>
        </div>

        <article class="panel dashboard-section">
          <div class="panel-heading"><div><h3>Recent system activity</h3><p><?= $isAdmin ? 'Latest actions performed across the system' : 'Your latest actions in the system' ?></p></div><?php if ($isAdmin): ?><a class="text-button" href="activity-logs.php">Open activity log →</a><?php endif; ?></div>
          <div class="recent-activity">
            <?php if (!$dashboard['recent_activity']): ?>
              <div class="empty-dashboard">No activity has been recorded yet.</div>
            <?php else: foreach ($dashboard['recent_activity'] as $activity): ?>
              <div class="activity-item">
                <span class="activity-dot">◷</span>
                <div class="activity-copy">
                  <b><?= e(str_replace('_', ' ', ucfirst((string)$activity['action']))) ?><?= $isAdmin && !empty($activity['full_name']) ? ' · ' . e($activity['full_name']) : '' ?></b>
                  <?php if (!empty($activity['details'])): ?><p><?= e($activity['details']) ?></p><?php endif; ?>
                  <small><?= e($formatDate($activity['created_at'])) ?></small>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </article>

        <div class="notice"><span>ⓘ</span><div><b>Archive reminder</b><p>Always confirm the uploaded image or PDF is clear and complete before submitting it for verification. Original score sheets must remain unchanged.</p></div></div>
      </section>
    </div>
    <footer class="footer"><span>© <?= date('Y') ?> Audu Bako College of Agriculture, Dambatta.</span><span>Digital Examination Score Sheet Archive</span></footer>
  </main>
</div>
<script>
(() => {
  const menuButton = document.getElementById('mobileMenu');
  const sidebar = document.getElementById('sidebar');
  if (!menuButton || !sidebar) return;
  menuButton.addEventListener('click', () => {
    const isOpen = sidebar.classList.toggle('open');
    menuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });
  document.addEventListener('click', (event) => {
    if (window.innerWidth <= 760 && sidebar.classList.contains('open') &&
        !sidebar.contains(event.target) && !menuButton.contains(event.target)) {
      sidebar.classList.remove('open');
      menuButton.setAttribute('aria-expanded', 'false');
    }
  });
})();
</script>
</body>
</html>
