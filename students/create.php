<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
requireAdminOrTeacher(); // Students cannot add other students

$activePage = 'students';
$pageTitle  = 'Add Student';
$userId  = (int)$_SESSION['user_id'];
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code   = sanitize($conn, $_POST['student_code'] ?? '');
    $name   = sanitize($conn, $_POST['name']         ?? '');
    $email  = trim($_POST['email'] ?? '');
    $dept   = sanitize($conn, $_POST['department']   ?? '');
    $sem    = (int)($_POST['semester'] ?? 1);

    if (strlen($code) < 2)  $errors[] = 'Student code is required.';
    if (strlen($name) < 2)  $errors[] = 'Name must be at least 2 characters.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
    if (strlen($dept) < 2)  $errors[] = 'Department is required.';
    if ($sem < 1 || $sem > 12) $errors[] = 'Semester must be between 1 and 12.';

    // Unique code
    if (empty($errors)) {
        $chk = $conn->prepare('SELECT id FROM students WHERE student_code = ?');
        $chk->bind_param('s', $code);
        $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) $errors[] = 'Student code already exists.';
        $chk->close();
    }
    if ($email && empty($errors)) {
        $chk = $conn->prepare('SELECT id FROM students WHERE email = ?');
        $chk->bind_param('s', $email);
        $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) $errors[] = 'Email already registered for another student.';
        $chk->close();
    }

    if (empty($errors)) {
        $emailVal = $email ?: null;
        $ins = $conn->prepare('INSERT INTO students (student_code, name, email, department, semester, enrolled_by) VALUES (?,?,?,?,?,?)');
        $ins->bind_param('ssssii', $code, $name, $emailVal, $dept, $sem, $userId);
        if ($ins->execute()) {
            setFlash('success', "Student \"$name\" added successfully.");
            header('Location: /ajim/students/index.php');
            exit;
        } else {
            $errors[] = 'Database error. Please try again.';
        }
        $ins->close();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" data-dismiss>⚠️ <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<div class="card" style="max-width:720px">
  <div class="card-header">
    <div class="card-title">Student Details</div>
    <a href="/ajim/students/index.php" class="btn btn-outline btn-sm">← Back</a>
  </div>

  <form method="POST" novalidate>
    <div class="form-grid">
      <div class="form-group">
        <label for="student_code">Student Code <span style="color:var(--red)">*</span></label>
        <input type="text" id="student_code" name="student_code" class="form-control"
               placeholder="e.g. STU2024001" required
               value="<?= htmlspecialchars($_POST['student_code'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="name">Full Name <span style="color:var(--red)">*</span></label>
        <input type="text" id="name" name="name" class="form-control"
               placeholder="e.g. Priya Sharma" required
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="email">Email (optional)</label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="student@university.edu"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="department">Department <span style="color:var(--red)">*</span></label>
        <input type="text" id="department" name="department" class="form-control"
               placeholder="e.g. Computer Science" required
               list="dept-list"
               value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
        <datalist id="dept-list">
          <option value="Computer Science">
          <option value="Information Technology">
          <option value="Electronics & Communication">
          <option value="Mechanical Engineering">
          <option value="Civil Engineering">
          <option value="Mathematics">
          <option value="Physics">
          <option value="Commerce">
        </datalist>
      </div>
      <div class="form-group">
        <label for="semester">Semester <span style="color:var(--red)">*</span></label>
        <select id="semester" name="semester" class="form-control" required>
          <?php for ($i = 1; $i <= 8; $i++): ?>
            <option value="<?= $i ?>" <?= (($_POST['semester'] ?? 1) == $i) ? 'selected' : '' ?>>
              Semester <?= $i ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">✓ Save Student</button>
      <a href="/ajim/students/index.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
