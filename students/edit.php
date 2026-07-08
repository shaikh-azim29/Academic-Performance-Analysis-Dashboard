<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
requireAdminOrTeacher(); // Students cannot edit student records

$activePage = 'students';
$pageTitle  = 'Edit Student';
$userId     = (int)$_SESSION['user_id'];
$id         = (int)($_GET['id'] ?? 0);
$errors     = [];

if (!$id) { header('Location: /ajim/students/index.php'); exit; }

// Fetch — admin can edit any, user only their own
if (isAdmin()) {
    $stmt = $conn->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
} else {
    $stmt = $conn->prepare('SELECT * FROM students WHERE id = ? AND enrolled_by = ? LIMIT 1');
    $stmt->bind_param('ii', $id, $userId);
}
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) { setFlash('danger', 'Student not found or access denied.'); header('Location: /ajim/students/index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code  = sanitize($conn, $_POST['student_code'] ?? '');
    $name  = sanitize($conn, $_POST['name']         ?? '');
    $email = trim($_POST['email'] ?? '');
    $dept  = sanitize($conn, $_POST['department']   ?? '');
    $sem   = (int)($_POST['semester'] ?? 1);

    if (strlen($code) < 2)  $errors[] = 'Student code is required.';
    if (strlen($name) < 2)  $errors[] = 'Name is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
    if (strlen($dept) < 2)  $errors[] = 'Department is required.';

    if (empty($errors)) {
        $chk = $conn->prepare('SELECT id FROM students WHERE student_code = ? AND id != ?');
        $chk->bind_param('si', $code, $id); $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) $errors[] = 'Student code already used.';
        $chk->close();
    }
    if (empty($errors)) {
        $emailVal = $email ?: null;
        $upd = $conn->prepare('UPDATE students SET student_code=?, name=?, email=?, department=?, semester=? WHERE id=?');
        $upd->bind_param('ssssii', $code, $name, $emailVal, $dept, $sem, $id);
        if ($upd->execute()) {
            setFlash('success', "Student updated.");
            header('Location: /ajim/students/index.php'); exit;
        }
        $errors[] = 'Update failed.';
        $upd->close();
    }
}

$d = empty($errors) ? $student : array_merge($student, $_POST);
include __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" data-dismiss>⚠️ <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<div class="card" style="max-width:720px">
  <div class="card-header">
    <div class="card-title">Edit Student</div>
    <a href="/ajim/students/index.php" class="btn btn-outline btn-sm">← Back</a>
  </div>

  <form method="POST" novalidate>
    <div class="form-grid">
      <div class="form-group">
        <label for="student_code">Student Code *</label>
        <input type="text" id="student_code" name="student_code" class="form-control" required value="<?= htmlspecialchars($d['student_code']) ?>">
      </div>
      <div class="form-group">
        <label for="name">Full Name *</label>
        <input type="text" id="name" name="name" class="form-control" required value="<?= htmlspecialchars($d['name']) ?>">
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($d['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="department">Department *</label>
        <input type="text" id="department" name="department" class="form-control" required list="dept-list" value="<?= htmlspecialchars($d['department']) ?>">
        <datalist id="dept-list">
          <option value="Computer Science"><option value="Information Technology">
          <option value="Electronics & Communication"><option value="Mechanical Engineering">
          <option value="Civil Engineering"><option value="Mathematics">
          <option value="Physics"><option value="Commerce">
        </datalist>
      </div>
      <div class="form-group">
        <label for="semester">Semester *</label>
        <select id="semester" name="semester" class="form-control">
          <?php for ($i = 1; $i <= 8; $i++): ?>
            <option value="<?= $i ?>" <?= ($d['semester'] == $i) ? 'selected' : '' ?>>Semester <?= $i ?></option>
          <?php endfor; ?>
        </select>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">✓ Update</button>
      <a href="/ajim/students/index.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
