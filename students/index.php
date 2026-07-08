<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
requireAdminOrTeacher(); // Students cannot manage other students

$activePage = 'students';
$pageTitle  = 'Students';
$userId  = (int)$_SESSION['user_id'];
$isAdmin = isAdmin();

// Search / filter
$search = sanitize($conn, $_GET['q'] ?? '');
$dept   = sanitize($conn, $_GET['dept'] ?? '');

$where  = 'WHERE 1=1';
$params = [];
$types  = '';

if ($search !== '') {
    $like = "%$search%";
    $where .= ' AND (s.name LIKE ? OR s.student_code LIKE ? OR s.email LIKE ?)';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
if ($dept !== '') {
    $where .= ' AND s.department = ?';
    $params[] = $dept;
    $types .= 's';
}

$sql  = "SELECT s.*, COUNT(r.id) AS record_count,
          ROUND(AVG(r.marks/r.max_marks*100),1) AS avg_pct
         FROM students s
         LEFT JOIN records r ON r.student_id = s.id
         $where
         GROUP BY s.id
         ORDER BY s.name ASC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Departments list for filter
$deptRes = $conn->query('SELECT DISTINCT department FROM students ORDER BY department');
$depts   = $deptRes->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>" data-dismiss><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<div class="d-flex align-center gap-12 mb-24" style="flex-wrap:wrap;justify-content:space-between">
  <h2 style="font-size:15px;font-weight:600">
    <?= count($students) ?> student<?= count($students) !== 1 ? 's' : '' ?> found
  </h2>
  <a href="/ajim/students/create.php" class="btn btn-primary btn-sm">＋ Add Student</a>
</div>

<!-- Filter bar -->
<form method="GET" class="filter-bar">
  <div class="search-box flex-1">
    <span class="search-icon">🔍</span>
    <input type="text" name="q" class="form-control" placeholder="Search name, code, email…"
           value="<?= htmlspecialchars($search) ?>">
  </div>
  <select name="dept" class="form-control" style="max-width:200px">
    <option value="">All Departments</option>
    <?php foreach ($depts as $d): ?>
      <option value="<?= htmlspecialchars($d['department']) ?>"
        <?= $dept === $d['department'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($d['department']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">Filter</button>
  <?php if ($search || $dept): ?>
    <a href="/ajim/students/index.php" class="btn btn-outline btn-sm">Clear</a>
  <?php endif; ?>
</form>

<div class="card">
  <?php if (empty($students)): ?>
    <div class="empty-state">
      <div class="empty-icon">🎓</div>
      <p>No students found. <a href="/ajim/students/create.php">Add the first student.</a></p>
    </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Code</th>
          <th>Name</th>
          <th>Department</th>
          <th>Semester</th>
          <th>Records</th>
          <th>Avg Score</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s):
          $pct = (float)($s['avg_pct'] ?? 0);
          $badgeClass = $pct >= 75 ? 'badge-green' : ($pct >= 45 ? 'badge-yellow' : ($pct > 0 ? 'badge-red' : 'badge-blue'));
        ?>
        <tr>
          <td><span class="badge badge-blue"><?= htmlspecialchars($s['student_code']) ?></span></td>
          <td>
            <div class="fw-600"><?= htmlspecialchars($s['name']) ?></div>
            <?php if ($s['email']): ?>
            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($s['email']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($s['department']) ?></td>
          <td>Sem <?= $s['semester'] ?></td>
          <td class="table-num"><?= $s['record_count'] ?></td>
          <td>
            <?php if ($s['record_count'] > 0): ?>
            <span class="badge <?= $badgeClass ?>"><?= $pct ?>%</span>
            <?php else: ?>
            <span class="text-muted" style="font-size:12px">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="d-flex gap-8" style="flex-wrap:wrap">
              <a href="/ajim/records/create.php?student_id=<?= $s['id'] ?>"
                 class="btn btn-primary btn-sm" title="Add marks for this student">＋ Record</a>
              <a href="/ajim/students/edit.php?id=<?= $s['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Edit">✏️</a>
              <a href="/ajim/records/index.php?student_id=<?= $s['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="View Records">📝</a>
              <a href="/ajim/students/delete.php?id=<?= $s['id'] ?>"
                 class="btn btn-danger btn-sm btn-icon"
                 onclick="return confirm('Delete <?= htmlspecialchars(addslashes($s['name'])) ?> and all their records?')"
                 title="Delete">🗑️</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
