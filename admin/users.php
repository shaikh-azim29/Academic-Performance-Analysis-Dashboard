<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
requireAdmin();

$activePage = 'admin-users';
$pageTitle  = 'Manage Users';

// Delete user
if (isset($_GET['delete'])) {
    $uid = (int)$_GET['delete'];
    if ($uid !== (int)$_SESSION['user_id']) {
        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        setFlash($stmt->affected_rows > 0 ? 'success' : 'danger', $stmt->affected_rows > 0 ? 'User deleted.' : 'Failed.');
        $stmt->close();
    } else {
        setFlash('warning', 'Cannot delete yourself.');
    }
    header('Location: /ajim/admin/users.php'); exit;
}

// Toggle role
if (isset($_GET['toggle_role'])) {
    $uid = (int)$_GET['toggle_role'];
    if ($uid !== (int)$_SESSION['user_id']) {
        $stmt = $conn->prepare("UPDATE users SET role = IF(role='admin','student','admin') WHERE id = ?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        setFlash('success', 'Role updated.');
        $stmt->close();
    }
    header('Location: /ajim/admin/users.php'); exit;
}

// Search
$search = sanitize($conn, $_GET['q'] ?? '');
$roleF  = sanitize($conn, $_GET['role'] ?? '');
$where  = 'WHERE 1=1';
$params = []; $types = '';

if ($search !== '') {
    $like = "%$search%";
    $where .= ' AND (name LIKE ? OR email LIKE ?)';
    $params[] = $like; $params[] = $like; $types .= 'ss';
}
if ($roleF !== '') {
    $where .= ' AND role = ?';
    $params[] = $roleF; $types .= 's';
}

$stmt = $conn->prepare(
    "SELECT u.*,
            s.student_code, s.department, s.semester,
            (SELECT COUNT(*) FROM records WHERE student_id = s.id) AS record_count
     FROM users u
     LEFT JOIN students s ON s.id = u.student_id
     $where
     ORDER BY u.created_at DESC"
);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>" data-dismiss><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<form method="GET" class="filter-bar mb-24">
  <div class="search-box flex-1">
    <span class="search-icon">🔍</span>
    <input type="text" name="q" class="form-control" placeholder="Search name or email…" value="<?= htmlspecialchars($search) ?>">
  </div>
  <select name="role" class="form-control" style="max-width:150px">
    <option value="">All Roles</option>
    <option value="admin" <?= $roleF === 'admin' ? 'selected' : '' ?>>Admin</option>
    <option value="student"  <?= $roleF === 'student'  ? 'selected' : '' ?>>Student</option>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">Filter</button>
  <a href="/ajim/admin/users.php" class="btn btn-outline btn-sm">Clear</a>
</form>

<div class="card">
  <div class="card-header">
    <div class="card-title">Users <span class="text-muted fw-600" style="font-size:13px">(<?= count($users) ?>)</span></div>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Roll No</th>
          <th>Department</th>
          <th>Sem</th>
          <th>Records</th>
          <th>Registered</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): $isSelf = ($u['id'] == $_SESSION['user_id']); ?>
        <tr>
          <td class="text-muted">#<?= $u['id'] ?></td>
          <td>
            <div class="fw-600"><?= htmlspecialchars($u['name']) ?></div>
            <?php if ($isSelf): ?><span class="badge badge-purple" style="font-size:10px">You</span><?php endif; ?>
          </td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><span class="badge <?= $u['role'] === 'admin' ? 'badge-purple' : 'badge-blue' ?>">
            <?= $u['role'] === 'admin' ? 'Admin' : 'Student' ?></span>
          </td>
          <!-- Roll Number -->
          <td>
            <?php if ($u['student_code']): ?>
              <code style="color:#4f8ef7;font-size:12px"><?= htmlspecialchars($u['student_code']) ?></code>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <!-- Department -->
          <td class="text-secondary" style="font-size:12px"><?= htmlspecialchars($u['department'] ?? '—') ?></td>
          <!-- Semester -->
          <td class="table-num"><?= $u['semester'] ? 'Sem ' . $u['semester'] : '—' ?></td>
          <!-- Records count -->
          <td class="table-num"><?= (int)$u['record_count'] ?></td>
          <td class="text-secondary"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          <td>
            <div class="d-flex gap-8" style="flex-wrap:wrap">
              <?php if ($u['role'] === 'student' && $u['student_id']): ?>
              <a href="/ajim/records/create.php?student_id=<?= (int)$u['student_id'] ?>"
                 class="btn btn-primary btn-sm" title="Add marks for this student">＋ Record</a>
              <?php endif; ?>
              <?php if (!$isSelf): ?>
              <a href="?toggle_role=<?= $u['id'] ?>"
                 class="btn btn-outline btn-sm"
                 onclick="return confirm('Toggle role for <?= htmlspecialchars(addslashes($u['name'])) ?>?')"
                 title="Toggle Role">🔄 Role</a>
              <a href="?delete=<?= $u['id'] ?>"
                 class="btn btn-danger btn-sm btn-icon"
                 onclick="return confirm('Delete user <?= htmlspecialchars(addslashes($u['name'])) ?>?')"
                 title="Delete">🗑️</a>
              <?php else: ?>
              <span class="text-muted" style="font-size:12px">—</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
