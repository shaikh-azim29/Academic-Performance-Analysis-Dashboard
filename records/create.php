<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
requireAdminOrTeacher(); // Only admins/teachers can add records

$activePage = 'records';
$pageTitle  = 'Add Record';
$userId  = (int)$_SESSION['user_id'];
$isAdmin = isAdmin();
$errors  = [];

// Pre-select student from quick-link (?student_id=X from admin/users.php)
$preSelectedStudent = (int)($_GET['student_id'] ?? 0);

// Fetch ALL students for dropdown — any admin/teacher can add records for any student
// (no enrolled_by filter: self-registered students must also appear here)
$stmt = $conn->prepare(
    'SELECT id, student_code, name, department, semester FROM students ORDER BY name ASC'
);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stuId    = (int)($_POST['student_id'] ?? 0);
    $subject  = sanitize($conn, $_POST['subject']   ?? '');
    $marks    = filter_var($_POST['marks']    ?? '', FILTER_VALIDATE_FLOAT);
    $maxMarks = filter_var($_POST['max_marks'] ?? 100, FILTER_VALIDATE_FLOAT);
    $examType = in_array($_POST['exam_type'] ?? '', ['midterm','final','assignment','quiz'])
                ? $_POST['exam_type'] : '';
    $examDate = $_POST['exam_date'] ?? '';
    $remarks  = sanitize($conn, $_POST['remarks']   ?? '');

    if (!$stuId)                       $errors[] = 'Select a student.';
    if (strlen($subject) < 2)          $errors[] = 'Subject is required.';
    if ($marks === false || $marks < 0) $errors[] = 'Invalid marks.';
    if (!$maxMarks || $maxMarks <= 0)  $errors[] = 'Max marks must be positive.';
    if ($marks > $maxMarks)            $errors[] = 'Marks cannot exceed max marks.';
    if (!$examType)                    $errors[] = 'Select exam type.';
    if (!$examDate || !strtotime($examDate)) $errors[] = 'Valid exam date required.';

    if (empty($errors)) {
        $ins = $conn->prepare('INSERT INTO records (student_id, subject, marks, max_marks, exam_type, exam_date, remarks, added_by) VALUES (?,?,?,?,?,?,?,?)');
        $ins->bind_param('isddsssi', $stuId, $subject, $marks, $maxMarks, $examType, $examDate, $remarks, $userId);
        if ($ins->execute()) {
            setFlash('success', 'Record added successfully.');
            header('Location: /ajim/records/index.php'); exit;
        }
        $errors[] = 'Database error.';
        $ins->close();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" data-dismiss>⚠️ <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<div class="card" style="max-width:760px">
  <div class="card-header">
    <div class="card-title">Performance Record Details</div>
    <a href="/ajim/records/index.php" class="btn btn-outline btn-sm">← Back</a>
  </div>

  <?php if (empty($students)): ?>
    <div class="alert alert-warning">
      ⚠️ No students found. <a href="/ajim/students/create.php">Add a student first.</a>
    </div>
  <?php else: ?>
  <form method="POST" novalidate>
    <div class="form-grid">
      <div class="form-group">
        <label for="student_id">Student <span style="color:var(--red)">*</span></label>
        <select id="student_id" name="student_id" class="form-control" required>
          <option value="">— Select Student —</option>
          <?php
            // Determine which student should be pre-selected:
            // 1. POST value (form re-submission with errors)
            // 2. GET ?student_id (admin quick-link from Users page)
            $selectedStuId = (int)($_POST['student_id'] ?? $preSelectedStudent);
          ?>
          <?php foreach ($students as $s): ?>
            <option value="<?= $s['id'] ?>"
              <?= ($selectedStuId === (int)$s['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['student_code'] . ' — ' . $s['name'] . ' (' . $s['department'] . ', Sem ' . $s['semester'] . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="subject">Subject <span style="color:var(--red)">*</span></label>
        <input type="text" id="subject" name="subject" class="form-control"
               placeholder="e.g. Mathematics" required list="subject-list"
               value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
        <datalist id="subject-list">
          <option value="Mathematics"><option value="Physics"><option value="Chemistry">
          <option value="Computer Science"><option value="English"><option value="Biology">
          <option value="History"><option value="Geography"><option value="Economics">
        </datalist>
      </div>

      <div class="form-group">
        <label for="marks">Marks Obtained <span style="color:var(--red)">*</span></label>
        <input type="number" id="marks" name="marks" class="form-control"
               placeholder="e.g. 78" min="0" step="0.01" required
               value="<?= htmlspecialchars($_POST['marks'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label for="max_marks">Max Marks <span style="color:var(--red)">*</span></label>
        <input type="number" id="max_marks" name="max_marks" class="form-control"
               value="<?= htmlspecialchars($_POST['max_marks'] ?? '100') ?>" min="1" step="0.01" required>
      </div>

      <div class="form-group">
        <label for="exam_type">Exam Type <span style="color:var(--red)">*</span></label>
        <select id="exam_type" name="exam_type" class="form-control" required>
          <option value="">— Select Type —</option>
          <?php foreach (['midterm','final','assignment','quiz'] as $t): ?>
            <option value="<?= $t ?>" <?= (($_POST['exam_type'] ?? '') === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="exam_date">Exam Date <span style="color:var(--red)">*</span></label>
        <input type="date" id="exam_date" name="exam_date" class="form-control" required
               max="<?= date('Y-m-d') ?>"
               value="<?= htmlspecialchars($_POST['exam_date'] ?? date('Y-m-d')) ?>">
      </div>

      <div class="form-group" style="grid-column:1/-1">
        <label for="remarks">Remarks (optional)</label>
        <textarea id="remarks" name="remarks" class="form-control"
                  placeholder="Optional notes…"><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- Live percentage preview -->
    <div class="card mt-16" style="background:var(--bg-dark);border-style:dashed;padding:16px">
      <div class="d-flex align-center gap-12">
        <span class="text-secondary" style="font-size:13px">Live Preview:</span>
        <span id="pctPreview" class="fw-700 text-accent">—</span>
        <span id="gradePreview" class="badge badge-blue">—</span>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">✓ Save Record</button>
      <a href="/ajim/records/index.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
  <?php endif; ?>
</div>

<script>
function updatePreview() {
  const m  = parseFloat(document.getElementById('marks').value);
  const mx = parseFloat(document.getElementById('max_marks').value);
  const pctEl   = document.getElementById('pctPreview');
  const gradeEl = document.getElementById('gradePreview');
  if (!isNaN(m) && !isNaN(mx) && mx > 0 && m >= 0 && m <= mx) {
    const pct = (m / mx * 100).toFixed(1);
    pctEl.textContent = pct + '%';
    const grades = [[90,'A+','badge-green'],[75,'A','badge-green'],[60,'B','badge-blue'],[45,'C','badge-yellow'],[33,'D','badge-yellow'],[0,'F','badge-red']];
    const [,g,bc] = grades.find(([t]) => pct >= t) || grades[grades.length-1];
    gradeEl.textContent = g;
    gradeEl.className = 'badge ' + bc;
  } else {
    pctEl.textContent = '—';
    gradeEl.textContent = '—';
    gradeEl.className = 'badge badge-blue';
  }
}
document.getElementById('marks')?.addEventListener('input', updatePreview);
document.getElementById('max_marks')?.addEventListener('input', updatePreview);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
