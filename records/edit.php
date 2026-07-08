<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
requireAdminOrTeacher(); // Only admins/teachers can edit records

$activePage = 'records';
$pageTitle  = 'Edit Record';
$userId  = (int)$_SESSION['user_id'];
$isAdmin = isAdmin();
$id      = (int)($_GET['id'] ?? 0);
$errors  = [];

if (!$id) { header('Location: /ajim/records/index.php'); exit; }

// Fetch record
if ($isAdmin) {
    $stmt = $conn->prepare('SELECT * FROM records WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
} else {
    $stmt = $conn->prepare('SELECT * FROM records WHERE id = ? AND added_by = ? LIMIT 1');
    $stmt->bind_param('ii', $id, $userId);
}
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$record) { setFlash('danger', 'Record not found.'); header('Location: /ajim/records/index.php'); exit; }

// Students for dropdown
$stuQ = $isAdmin
    ? 'SELECT id, student_code, name FROM students ORDER BY name'
    : 'SELECT id, student_code, name FROM students WHERE enrolled_by = ? ORDER BY name';
$stmt = $conn->prepare($stuQ);
if (!$isAdmin) { $stmt->bind_param('i', $userId); }
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stuId    = (int)($_POST['student_id'] ?? 0);
    $subject  = sanitize($conn, $_POST['subject']   ?? '');
    $marks    = filter_var($_POST['marks']     ?? '', FILTER_VALIDATE_FLOAT);
    $maxMarks = filter_var($_POST['max_marks'] ?? 100, FILTER_VALIDATE_FLOAT);
    $examType = in_array($_POST['exam_type'] ?? '', ['midterm','final','assignment','quiz']) ? $_POST['exam_type'] : '';
    $examDate = $_POST['exam_date'] ?? '';
    $remarks  = sanitize($conn, $_POST['remarks'] ?? '');

    if (!$stuId)              $errors[] = 'Select a student.';
    if (strlen($subject) < 2) $errors[] = 'Subject required.';
    if ($marks === false)     $errors[] = 'Invalid marks.';
    if ($marks > $maxMarks)   $errors[] = 'Marks exceed max.';
    if (!$examType)           $errors[] = 'Select exam type.';
    if (!$examDate)           $errors[] = 'Date required.';

    if (empty($errors)) {
        $upd = $conn->prepare('UPDATE records SET student_id=?,subject=?,marks=?,max_marks=?,exam_type=?,exam_date=?,remarks=? WHERE id=?');
        $upd->bind_param('isddsssi', $stuId, $subject, $marks, $maxMarks, $examType, $examDate, $remarks, $id);
        if ($upd->execute()) { setFlash('success', 'Record updated.'); header('Location: /ajim/records/index.php'); exit; }
        $errors[] = 'Update failed.';
        $upd->close();
    }
}

$d = empty($errors) ? $record : array_merge($record, $_POST);
include __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" data-dismiss>⚠️ <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<div class="card" style="max-width:760px">
  <div class="card-header">
    <div class="card-title">Edit Performance Record</div>
    <a href="/ajim/records/index.php" class="btn btn-outline btn-sm">← Back</a>
  </div>
  <form method="POST" novalidate>
    <div class="form-grid">
      <div class="form-group">
        <label>Student *</label>
        <select name="student_id" class="form-control" required>
          <?php foreach ($students as $s): ?>
            <option value="<?= $s['id'] ?>" <?= ($d['student_id'] == $s['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['student_code'] . ' — ' . $s['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Subject *</label>
        <input type="text" name="subject" class="form-control" required list="subject-list"
               value="<?= htmlspecialchars($d['subject']) ?>">
        <datalist id="subject-list">
          <option value="Mathematics"><option value="Physics"><option value="Chemistry">
          <option value="Computer Science"><option value="English"><option value="Biology">
        </datalist>
      </div>
      <div class="form-group">
        <label>Marks *</label>
        <input type="number" id="marks" name="marks" class="form-control" step="0.01" min="0" required
               value="<?= htmlspecialchars($d['marks']) ?>">
      </div>
      <div class="form-group">
        <label>Max Marks *</label>
        <input type="number" id="max_marks" name="max_marks" class="form-control" step="0.01" min="1" required
               value="<?= htmlspecialchars($d['max_marks']) ?>">
      </div>
      <div class="form-group">
        <label>Exam Type *</label>
        <select name="exam_type" class="form-control" required>
          <?php foreach (['midterm','final','assignment','quiz'] as $t): ?>
            <option value="<?= $t ?>" <?= ($d['exam_type'] === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Exam Date *</label>
        <input type="date" name="exam_date" class="form-control" required
               value="<?= htmlspecialchars($d['exam_date']) ?>">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Remarks</label>
        <textarea name="remarks" class="form-control"><?= htmlspecialchars($d['remarks'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="card mt-16" style="background:var(--bg-dark);border-style:dashed;padding:16px">
      <div class="d-flex align-center gap-12">
        <span class="text-secondary" style="font-size:13px">Live Preview:</span>
        <span id="pctPreview" class="fw-700 text-accent">—</span>
        <span id="gradePreview" class="badge badge-blue">—</span>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">✓ Update</button>
      <a href="/ajim/records/index.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<script>
function updatePreview() {
  const m = parseFloat(document.getElementById('marks').value);
  const mx = parseFloat(document.getElementById('max_marks').value);
  const pEl = document.getElementById('pctPreview'), gEl = document.getElementById('gradePreview');
  if (!isNaN(m) && !isNaN(mx) && mx > 0 && m >= 0 && m <= mx) {
    const pct = (m/mx*100).toFixed(1);
    pEl.textContent = pct + '%';
    const g = pct>=90?['A+','badge-green']:pct>=75?['A','badge-green']:pct>=60?['B','badge-blue']:pct>=45?['C','badge-yellow']:pct>=33?['D','badge-yellow']:['F','badge-red'];
    gEl.textContent = g[0]; gEl.className = 'badge ' + g[1];
  }
}
document.getElementById('marks')?.addEventListener('input', updatePreview);
document.getElementById('max_marks')?.addEventListener('input', updatePreview);
updatePreview();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
