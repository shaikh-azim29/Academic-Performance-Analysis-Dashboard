<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$activePage = 'records';
$pageTitle  = 'Performance Records';
$userId     = (int)$_SESSION['user_id'];
$isAdmin    = isAdmin();
$isStudent  = isStudent();

// Students are scoped to their own student record
$myStudentId = $isStudent ? (int)($_SESSION['student_id'] ?? 0) : 0;

// Filters
$search    = sanitize($conn, $_GET['q']          ?? '');
$subject   = sanitize($conn, $_GET['subject']    ?? '');
$examType  = sanitize($conn, $_GET['exam_type']  ?? '');
$minMarks  = is_numeric($_GET['min_pct'] ?? '') ? (float)$_GET['min_pct'] : '';
$maxMarks  = is_numeric($_GET['max_pct'] ?? '') ? (float)$_GET['max_pct'] : '';
$stuFilter = is_numeric($_GET['student_id'] ?? '') ? (int)$_GET['student_id'] : 0;

// Base scope:
// - admin: all records
// - student: only records for their own student_id
// - (no more 'added_by' scoping for teachers since teachers are now admins)
if ($isStudent) {
    $baseWhere = $myStudentId ? 'WHERE r.student_id = ?' : 'WHERE 1=0';
    $params    = $myStudentId ? [$myStudentId] : [];
    $types     = $myStudentId ? 'i' : '';
} else {
    $baseWhere = 'WHERE 1=1';
    $params    = [];
    $types     = '';
}

if ($search !== '') {
    $like = "%$search%";
    $baseWhere .= ' AND (s.name LIKE ? OR s.student_code LIKE ? OR r.subject LIKE ?)';
    array_push($params, $like, $like, $like);
    $types .= 'sss';
}
if ($subject !== '') {
    $baseWhere .= ' AND r.subject = ?';
    $params[] = $subject; $types .= 's';
}
if ($examType !== '') {
    $baseWhere .= ' AND r.exam_type = ?';
    $params[] = $examType; $types .= 's';
}
if ($minMarks !== '') {
    $baseWhere .= ' AND (r.marks/r.max_marks*100) >= ?';
    $params[] = $minMarks; $types .= 'd';
}
if ($maxMarks !== '') {
    $baseWhere .= ' AND (r.marks/r.max_marks*100) <= ?';
    $params[] = $maxMarks; $types .= 'd';
}
// Non-students can filter by a specific student
if (!$isStudent && $stuFilter) {
    $baseWhere .= ' AND r.student_id = ?';
    $params[] = $stuFilter; $types .= 'i';
}

$sql = "SELECT r.*, s.name AS student_name, s.student_code
        FROM records r
        JOIN students s ON s.id = r.student_id
        $baseWhere
        ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Subjects list for dropdown — scoped to student's own records if student
if ($isStudent && $myStudentId) {
    $subStmt = $conn->prepare('SELECT DISTINCT subject FROM records WHERE student_id = ? ORDER BY subject');
    $subStmt->bind_param('i', $myStudentId);
    $subStmt->execute();
    $subjects = $subStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $subStmt->close();
} elseif ($isStudent) {
    $subjects = [];
} else {
    $subRes   = $conn->query('SELECT DISTINCT subject FROM records ORDER BY subject');
    $subjects = $subRes->fetch_all(MYSQLI_ASSOC);
}

function gradeBadge(float $pct): array {
    if ($pct >= 90) return ['A+', 'badge-green'];
    if ($pct >= 75) return ['A',  'badge-green'];
    if ($pct >= 60) return ['B',  'badge-blue'];
    if ($pct >= 45) return ['C',  'badge-yellow'];
    if ($pct >= 33) return ['D',  'badge-yellow'];
    return ['F', 'badge-red'];
}

include __DIR__ . '/../includes/header.php';
?>

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>" data-dismiss><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<div class="d-flex align-center gap-12 mb-24" style="flex-wrap:wrap;justify-content:space-between">
  <h2 style="font-size:15px;font-weight:600"><?= count($records) ?> record<?= count($records) !== 1 ? 's' : '' ?></h2>
  <?php if (!$isStudent): ?>
  <a href="/ajim/records/create.php" class="btn btn-primary btn-sm">＋ Add Record</a>
  <?php endif; ?>
</div>

<!-- Filters -->
<form method="GET" class="filter-bar" style="flex-wrap:wrap">
  <div class="search-box" style="min-width:200px;flex:2">
    <span class="search-icon">🔍</span>
    <input type="text" name="q" class="form-control" placeholder="Search <?= $isStudent ? 'subject' : 'student or subject' ?>…" value="<?= htmlspecialchars($search) ?>">
  </div>
  <select name="subject" class="form-control" style="max-width:180px">
    <option value="">All Subjects</option>
    <?php foreach ($subjects as $sub): ?>
      <option value="<?= htmlspecialchars($sub['subject']) ?>" <?= $subject === $sub['subject'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($sub['subject']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select name="exam_type" class="form-control" style="max-width:140px">
    <option value="">All Types</option>
    <?php foreach (['midterm','final','assignment','quiz'] as $t): ?>
      <option value="<?= $t ?>" <?= $examType === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="number" name="min_pct" class="form-control" placeholder="Min %" style="max-width:90px" value="<?= htmlspecialchars($_GET['min_pct'] ?? '') ?>">
  <input type="number" name="max_pct" class="form-control" placeholder="Max %" style="max-width:90px" value="<?= htmlspecialchars($_GET['max_pct'] ?? '') ?>">
  <button type="submit" class="btn btn-primary btn-sm">Filter</button>
  <a href="/ajim/records/index.php" class="btn btn-outline btn-sm">Clear</a>
</form>

<?php if ($isStudent): ?>
<div class="alert alert-info" style="background:rgba(79,142,247,.1);border:1px solid rgba(79,142,247,.3);color:var(--accent);border-radius:8px;padding:10px 16px;font-size:13px;margin-bottom:16px">
  📖 You are viewing your own performance records (read-only).
</div>
<?php endif; ?>

<div class="card">
  <?php if (empty($records)): ?>
    <div class="empty-state">
      <div class="empty-icon">📝</div>
      <p><?= $isStudent ? 'No performance records found for your account.' : 'No records found. <a href="/ajim/records/create.php">Add a record.</a>' ?></p>
    </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table id="recordsTable">
      <thead>
        <tr>
          <th>#</th>
          <?php if (!$isStudent): ?><th>Student</th><?php endif; ?>
          <th>Subject</th>
          <th>Marks</th>
          <th>Percentage</th>
          <th>Grade</th>
          <th>Type</th>
          <th>Date</th>
          <?php if (!$isStudent): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $i => $r):
          $pct = round($r['marks'] / $r['max_marks'] * 100, 1);
          [$grade, $bc] = gradeBadge($pct);
        ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <?php if (!$isStudent): ?>
          <td>
            <div class="fw-600"><?= htmlspecialchars($r['student_name']) ?></div>
            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($r['student_code']) ?></div>
          </td>
          <?php endif; ?>
          <td><?= htmlspecialchars($r['subject']) ?></td>
          <td class="table-num"><?= $r['marks'] ?> / <?= $r['max_marks'] ?></td>
          <td>
            <div class="d-flex align-center gap-8">
              <div class="progress flex-1"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
              <span class="table-num" style="font-size:12px;min-width:38px"><?= $pct ?>%</span>
            </div>
          </td>
          <td><span class="badge <?= $bc ?>"><?= $grade ?></span></td>
          <td><span class="badge badge-purple"><?= ucfirst($r['exam_type']) ?></span></td>
          <td class="text-secondary"><?= date('M j, Y', strtotime($r['exam_date'])) ?></td>
          <?php if (!$isStudent): ?>
          <td>
            <div class="d-flex gap-8">
              <a href="/ajim/records/edit.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Edit">✏️</a>
              <a href="/ajim/records/delete.php?id=<?= $r['id'] ?>"
                 class="btn btn-danger btn-sm btn-icon"
                 onclick="return confirm('Delete this record?')" title="Delete">🗑️</a>
            </div>
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
