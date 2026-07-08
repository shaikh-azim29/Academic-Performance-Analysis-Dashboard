<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$activePage = 'reports';
$pageTitle  = 'Reports';
$userId     = (int)$_SESSION['user_id'];
$isAdmin    = isAdmin();
$isStudent  = isStudent();

// Students are scoped to their own student record
$myStudentId = $isStudent ? (int)($_SESSION['student_id'] ?? 0) : 0;

// Generate report action — admin only
if (!$isStudent && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $stuId = (int)($_POST['student_id'] ?? 0);
    if ($stuId) {
        $agg = $conn->prepare(
            'SELECT COUNT(*) AS total, ROUND(AVG(marks/max_marks*100),2) AS avg_pct,
                    MAX(marks) AS hi, MIN(marks) AS lo
             FROM records WHERE student_id = ?'
        );
        $agg->bind_param('i', $stuId);
        $agg->execute();
        $agg_data = $agg->get_result()->fetch_assoc();
        $agg->close();

        if ($agg_data && $agg_data['total'] > 0) {
            $avg  = (float)$agg_data['avg_pct'];
            $status = $avg >= 75 ? 'Excellent' : ($avg >= 60 ? 'Good' : ($avg >= 45 ? 'Average' : ($avg >= 33 ? 'Below Average' : 'Fail')));
            $grade  = $avg >= 90 ? 'A+' : ($avg >= 75 ? 'A' : ($avg >= 60 ? 'B' : ($avg >= 45 ? 'C' : ($avg >= 33 ? 'D' : 'F'))));

            $ins = $conn->prepare(
                'INSERT INTO reports (student_id, report_date, total_subjects, avg_percentage, highest_marks, lowest_marks, grade, performance_status, generated_by)
                 VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->bind_param('iidddssi', $stuId, $agg_data['total'], $avg, $agg_data['hi'], $agg_data['lo'], $grade, $status, $userId);
            if ($ins->execute()) { setFlash('success', 'Report generated.'); }
            $ins->close();
        } else {
            setFlash('warning', 'No records found for this student.');
        }
    }
    header('Location: /ajim/reports/index.php'); exit;
}

// Delete report — admin only
if (!$isStudent && isset($_GET['delete'])) {
    $rid = (int)$_GET['delete'];
    $del = $isAdmin
        ? $conn->prepare('DELETE FROM reports WHERE id = ?')
        : $conn->prepare('DELETE FROM reports WHERE id = ? AND generated_by = ?');
    if ($isAdmin) { $del->bind_param('i', $rid); }
    else          { $del->bind_param('ii', $rid, $userId); }
    $del->execute();
    setFlash($del->affected_rows > 0 ? 'success' : 'danger',
             $del->affected_rows > 0 ? 'Report deleted.' : 'Delete failed.');
    $del->close();
    header('Location: /ajim/reports/index.php'); exit;
}

// Fetch reports
$search  = sanitize($conn, $_GET['q']      ?? '');
$statusF = sanitize($conn, $_GET['status'] ?? '');

// Scope: students see only their own reports
if ($isStudent) {
    $where  = $myStudentId ? 'WHERE rp.student_id = ?' : 'WHERE 1=0';
    $params = $myStudentId ? [$myStudentId] : [];
    $types  = $myStudentId ? 'i' : '';
} else {
    $where  = 'WHERE 1=1';
    $params = [];
    $types  = '';
}

if ($search !== '') {
    $like = "%$search%";
    $where .= ' AND (s.name LIKE ? OR s.student_code LIKE ?)';
    $params[] = $like; $params[] = $like; $types .= 'ss';
}
if ($statusF !== '') {
    $where .= ' AND rp.performance_status = ?';
    $params[] = $statusF; $types .= 's';
}

$sql = "SELECT rp.*, s.name AS student_name, s.student_code, s.department
        FROM reports rp
        JOIN students s ON s.id = rp.student_id
        $where
        ORDER BY rp.created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Students for generate dropdown — admin/teacher only
$students = [];
if (!$isStudent) {
    $stuQ = 'SELECT id, student_code, name FROM students ORDER BY name';
    $stmt = $conn->prepare($stuQ);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$statusBadge = [
    'Excellent'    => 'badge-green',
    'Good'         => 'badge-blue',
    'Average'      => 'badge-yellow',
    'Below Average'=> 'badge-yellow',
    'Fail'         => 'badge-red',
];

include __DIR__ . '/../includes/header.php';
?>

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>" data-dismiss><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<?php if ($isStudent): ?>
<div class="alert alert-info" style="background:rgba(79,142,247,.1);border:1px solid rgba(79,142,247,.3);color:var(--accent);border-radius:8px;padding:10px 16px;font-size:13px;margin-bottom:16px">
  📖 You are viewing your own performance reports (read-only).
</div>
<?php else: ?>
<!-- Generate Report Card — admin/teacher only -->
<div class="card mb-24" style="max-width:640px">
  <div class="card-header"><div class="card-title">⚡ Generate New Report</div></div>
  <form method="POST" class="d-flex gap-12 align-center" style="flex-wrap:wrap">
    <select name="student_id" class="form-control flex-1" required>
      <option value="">— Select Student —</option>
      <?php foreach ($students as $s): ?>
        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['student_code'] . ' — ' . $s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" name="generate" class="btn btn-primary">Generate</button>
  </form>
</div>
<?php endif; ?>

<!-- Filter -->
<form method="GET" class="filter-bar mb-24">
  <div class="search-box flex-1">
    <span class="search-icon">🔍</span>
    <input type="text" name="q" class="form-control" placeholder="Search student…" value="<?= htmlspecialchars($search) ?>">
  </div>
  <select name="status" class="form-control" style="max-width:180px">
    <option value="">All Statuses</option>
    <?php foreach (array_keys($statusBadge) as $st): ?>
      <option value="<?= $st ?>" <?= $statusF === $st ? 'selected' : '' ?>><?= $st ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">Filter</button>
  <a href="/ajim/reports/index.php" class="btn btn-outline btn-sm">Clear</a>
</form>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <?= $isStudent ? 'My Reports' : 'All Reports' ?>
      <span class="text-muted fw-600" style="font-size:13px">(<?= count($reports) ?>)</span>
    </div>
  </div>

  <?php if (empty($reports)): ?>
    <div class="empty-state">
      <div class="empty-icon">📋</div>
      <p><?= $isStudent ? 'No reports available for your account yet.' : 'No reports yet. Generate one above.' ?></p>
    </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <?php if (!$isStudent): ?><th>Student</th><th>Department</th><?php endif; ?>
          <th>Avg Score</th>
          <th>Grade</th>
          <th>Status</th>
          <th>Subjects</th>
          <th>High / Low</th>
          <th>Date</th>
          <?php if (!$isStudent): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reports as $r):
          $bc = $statusBadge[$r['performance_status']] ?? 'badge-blue';
        ?>
        <tr>
          <?php if (!$isStudent): ?>
          <td>
            <div class="fw-600"><?= htmlspecialchars($r['student_name']) ?></div>
            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($r['student_code']) ?></div>
          </td>
          <td><?= htmlspecialchars($r['department']) ?></td>
          <?php endif; ?>
          <td>
            <div class="d-flex align-center gap-8">
              <div class="progress flex-1"><div class="progress-bar" style="width:<?= $r['avg_percentage'] ?>%"></div></div>
              <span class="table-num" style="font-size:12px"><?= $r['avg_percentage'] ?>%</span>
            </div>
          </td>
          <td><span class="badge <?= $bc ?>"><?= htmlspecialchars($r['grade'] ?? '—') ?></span></td>
          <td><span class="badge <?= $bc ?>"><?= htmlspecialchars($r['performance_status']) ?></span></td>
          <td class="table-num"><?= $r['total_subjects'] ?></td>
          <td>
            <span class="text-green fw-600"><?= $r['highest_marks'] ?></span>
            <span class="text-muted"> / </span>
            <span class="text-red fw-600"><?= $r['lowest_marks'] ?></span>
          </td>
          <td class="text-secondary"><?= date('M j, Y', strtotime($r['report_date'])) ?></td>
          <?php if (!$isStudent): ?>
          <td>
            <a href="?delete=<?= $r['id'] ?>"
               class="btn btn-danger btn-sm btn-icon"
               onclick="return confirm('Delete this report?')" title="Delete">🗑️</a>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
