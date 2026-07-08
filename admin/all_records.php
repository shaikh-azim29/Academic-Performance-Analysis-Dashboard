<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
requireAdmin();

$activePage = 'admin-records';
$pageTitle  = 'All Records (Admin)';

// Filters
$search   = sanitize($conn, $_GET['q']         ?? '');
$subject  = sanitize($conn, $_GET['subject']   ?? '');
$examType = sanitize($conn, $_GET['exam_type'] ?? '');
$userF    = is_numeric($_GET['user_id'] ?? '') ? (int)$_GET['user_id'] : 0;

$where  = 'WHERE 1=1';
$params = []; $types = '';

if ($search !== '') {
    $like = "%$search%";
    $where .= ' AND (s.name LIKE ? OR s.student_code LIKE ? OR r.subject LIKE ?)';
    array_push($params, $like, $like, $like); $types .= 'sss';
}
if ($subject !== '') { $where .= ' AND r.subject = ?'; $params[] = $subject; $types .= 's'; }
if ($examType !== '') { $where .= ' AND r.exam_type = ?'; $params[] = $examType; $types .= 's'; }
if ($userF) { $where .= ' AND r.added_by = ?'; $params[] = $userF; $types .= 'i'; }

$sql = "SELECT r.*, s.name AS student_name, s.student_code, u.name AS added_by_name
        FROM records r
        JOIN students s ON s.id = r.student_id
        JOIN users u ON u.id = r.added_by
        $where
        ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$subRes   = $conn->query('SELECT DISTINCT subject FROM records ORDER BY subject');
$subjects = $subRes->fetch_all(MYSQLI_ASSOC);
$userRes  = $conn->query('SELECT id, name FROM users ORDER BY name');
$allUsers = $userRes->fetch_all(MYSQLI_ASSOC);

function gradeBadge(float $pct): array {
    if ($pct >= 90) return ['A+','badge-green']; if ($pct >= 75) return ['A','badge-green'];
    if ($pct >= 60) return ['B','badge-blue'];   if ($pct >= 45) return ['C','badge-yellow'];
    if ($pct >= 33) return ['D','badge-yellow']; return ['F','badge-red'];
}

include __DIR__ . '/../includes/header.php';
?>

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>" data-dismiss><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<form method="GET" class="filter-bar mb-24" style="flex-wrap:wrap">
  <div class="search-box flex-1">
    <span class="search-icon">🔍</span>
    <input type="text" name="q" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
  </div>
  <select name="subject" class="form-control" style="max-width:160px">
    <option value="">All Subjects</option>
    <?php foreach ($subjects as $sub): ?>
      <option value="<?= htmlspecialchars($sub['subject']) ?>" <?= $subject === $sub['subject'] ? 'selected' : '' ?>><?= htmlspecialchars($sub['subject']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="exam_type" class="form-control" style="max-width:130px">
    <option value="">All Types</option>
    <?php foreach (['midterm','final','assignment','quiz'] as $t): ?>
      <option value="<?= $t ?>" <?= $examType === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="user_id" class="form-control" style="max-width:160px">
    <option value="">All Users</option>
    <?php foreach ($allUsers as $u): ?>
      <option value="<?= $u['id'] ?>" <?= $userF === $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">Filter</button>
  <a href="/ajim/admin/all_records.php" class="btn btn-outline btn-sm">Clear</a>
</form>

<div class="card">
  <div class="card-header">
    <div class="card-title">Records <span class="text-muted fw-600" style="font-size:13px">(<?= count($records) ?>)</span></div>
  </div>
  <?php if (empty($records)): ?>
    <div class="empty-state"><div class="empty-icon">📭</div><p>No records match.</p></div>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Subject</th>
          <th>Marks</th>
          <th>%</th>
          <th>Grade</th>
          <th>Type</th>
          <th>Added By</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $r):
          $pct = round($r['marks']/$r['max_marks']*100, 1);
          [$gr,$bc] = gradeBadge($pct);
        ?>
        <tr>
          <td>
            <div class="fw-600"><?= htmlspecialchars($r['student_name']) ?></div>
            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($r['student_code']) ?></div>
          </td>
          <td><?= htmlspecialchars($r['subject']) ?></td>
          <td class="table-num"><?= $r['marks'] ?>/<?= $r['max_marks'] ?></td>
          <td>
            <div class="d-flex align-center gap-8">
              <div class="progress flex-1"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
              <span style="font-size:12px;font-weight:600;min-width:36px"><?= $pct ?>%</span>
            </div>
          </td>
          <td><span class="badge <?= $bc ?>"><?= $gr ?></span></td>
          <td><span class="badge badge-purple"><?= ucfirst($r['exam_type']) ?></span></td>
          <td class="text-secondary"><?= htmlspecialchars($r['added_by_name']) ?></td>
          <td class="text-secondary"><?= date('M j, Y', strtotime($r['exam_date'])) ?></td>
          <td>
            <div class="d-flex gap-8">
              <a href="/ajim/records/edit.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Edit">✏️</a>
              <a href="/ajim/records/delete.php?id=<?= $r['id'] ?>"
                 class="btn btn-danger btn-sm btn-icon"
                 onclick="return confirm('Delete record?')" title="Delete">🗑️</a>
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
