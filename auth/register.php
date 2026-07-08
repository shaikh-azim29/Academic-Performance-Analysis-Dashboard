<?php
require_once __DIR__ . '/../config/session.php';

if (isLoggedIn()) {
    header('Location: /ajim/dashboard.php');
    exit;
}

$errors  = [];
$success = '';

// Semester options
$semesterOptions = [1,2,3,4,5,6,7,8];

// Department options
$deptOptions = [
    'Computer Science',
    'Information Technology',
    'Electronics & Communication',
    'Mechanical Engineering',
    'Civil Engineering',
    'Electrical Engineering',
    'Business Administration',
    'Commerce',
    'Arts & Humanities',
    'Science',
    'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/db.php';

    $name       = sanitize($conn, $_POST['name']       ?? '');
    $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $department = sanitize($conn, $_POST['department']  ?? '');
    $semester   = (int)($_POST['semester'] ?? 1);
    $password   = $_POST['password']  ?? '';
    $confirm    = $_POST['confirm']   ?? '';

    // ── Validation ─────────────────────────────────────────────
    if (strlen($name) < 2) {
        $errors[] = 'Full name must be at least 2 characters.';
    }
    if (!$email) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!in_array($department, $deptOptions, true)) {
        $errors[] = 'Please select a valid department.';
    }
    if ($semester < 1 || $semester > 8) {
        $errors[] = 'Please select a valid semester.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // ── Duplicate email check and existing student check ───────
    $existingStudentId = null;
    $rollNo = '';
    if (empty($errors)) {
        // Check users table
        $chk = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $chk->bind_param('s', $email);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $errors[] = 'This email is already registered.';
        }
        $chk->close();

        // Check if student record already exists under this email
        if (empty($errors)) {
            $chkStudent = $conn->prepare('SELECT id, student_code FROM students WHERE email = ? LIMIT 1');
            $chkStudent->bind_param('s', $email);
            $chkStudent->execute();
            $chkStudent->store_result();
            if ($chkStudent->num_rows > 0) {
                $chkStudent->bind_result($studentId, $existingRollNo);
                $chkStudent->fetch();
                $chkStudent->close();

                // Check if this existing student record is already linked to a user account
                $chkLink = $conn->prepare('SELECT id FROM users WHERE student_id = ? LIMIT 1');
                $chkLink->bind_param('i', $studentId);
                $chkLink->execute();
                $chkLink->store_result();
                if ($chkLink->num_rows > 0) {
                    $errors[] = 'This student email is already registered and linked to a user account.';
                }
                $chkLink->close();

                if (empty($errors)) {
                    $existingStudentId = $studentId;
                    $rollNo = $existingRollNo;
                }
            } else {
                $chkStudent->close();
            }
        }
    }

    // ── Create user + student in one transaction ────────────────
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            if ($existingStudentId !== null) {
                // Scenario A: Student record already exists. Link user directly.
                $insUser = $conn->prepare(
                    'INSERT INTO users (name, email, password, role, student_id) VALUES (?, ?, ?, "student", ?)'
                );
                $insUser->bind_param('sssi', $name, $email, $hash, $existingStudentId);
                $insUser->execute();
                $userId = $conn->insert_id;
                $insUser->close();
            } else {
                // Scenario B: No student record exists. Create both user and student.
                // 1. Insert user
                $insUser = $conn->prepare(
                    'INSERT INTO users (name, email, password, role, student_id) VALUES (?, ?, ?, "student", NULL)'
                );
                $insUser->bind_param('sss', $name, $email, $hash);
                $insUser->execute();
                $userId = $conn->insert_id;
                $insUser->close();

                // Resolve enrolled_by: use first admin's ID or own userId
                $adminRow = $conn->query("SELECT id FROM users WHERE role='admin' ORDER BY id ASC LIMIT 1")->fetch_assoc();
                $enrolledBy = $adminRow ? (int)$adminRow['id'] : $userId;

                // 2. Auto-generate roll number: STU-YYYY-NNNN
                $year     = date('Y');
                $countRes = $conn->query("SELECT COUNT(*) AS cnt FROM students WHERE student_code LIKE 'STU-{$year}-%'");
                $seq      = (int)$countRes->fetch_assoc()['cnt'] + 1;
                $rollNo   = sprintf('STU-%s-%04d', $year, $seq);

                // 3. Insert student record
                $insStudent = $conn->prepare(
                    'INSERT INTO students (student_code, name, email, department, semester, enrolled_by)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $insStudent->bind_param('ssssis', $rollNo, $name, $email, $department, $semester, $enrolledBy);
                $insStudent->execute();
                $studentId = $conn->insert_id;
                $insStudent->close();

                // 4. Link user ↔ student
                $upd = $conn->prepare('UPDATE users SET student_id = ? WHERE id = ?');
                $upd->bind_param('ii', $studentId, $userId);
                $upd->execute();
                $upd->close();
            }

            $conn->commit();

            setFlash('success', "Account created! Your Roll Number is <strong>{$rollNo}</strong>. Please log in.");
            header('Location: /ajim/auth/login.php');
            exit;

        } catch (Throwable $e) {
            $conn->rollback();
            $errors[] = 'Registration failed. Please try again. (' . $e->getMessage() . ')';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Create your APAD student account">
  <title>Register | APAD</title>
  <link rel="stylesheet" href="/ajim/assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card" style="max-width:520px">
    <div class="auth-logo">📊</div>
    <h1 class="auth-title">Create Account</h1>
    <p class="auth-sub">Register as a student — your roll number is assigned automatically</p>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger" data-dismiss>
        ⚠️ <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
      </div>
    <?php endif; ?>

    <form method="POST" id="regForm" novalidate>

      <!-- Full Name -->
      <div class="form-group mb-16">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" class="form-control"
               placeholder="John Doe" required
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>

      <!-- Email -->
      <div class="form-group mb-16">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="you@example.com" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>

      <!-- Department + Semester side by side -->
      <div class="d-flex gap-12 mb-16" style="flex-wrap:wrap">
        <div class="form-group flex-1" style="min-width:200px">
          <label for="department">Department</label>
          <select id="department" name="department" class="form-control" required>
            <option value="">— Select Department —</option>
            <?php foreach ($deptOptions as $d): ?>
              <option value="<?= htmlspecialchars($d) ?>"
                <?= (($_POST['department'] ?? '') === $d) ? 'selected' : '' ?>>
                <?= htmlspecialchars($d) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" style="min-width:120px">
          <label for="semester">Semester</label>
          <select id="semester" name="semester" class="form-control" required>
            <?php foreach ($semesterOptions as $s): ?>
              <option value="<?= $s ?>"
                <?= ((int)($_POST['semester'] ?? 1) === $s) ? 'selected' : '' ?>>
                Sem <?= $s ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Roll number info badge -->
      <div style="background:rgba(79,142,247,.08);border:1px solid rgba(79,142,247,.25);
                  border-radius:8px;padding:10px 14px;font-size:12px;color:#8b949e;margin-bottom:16px">
        🎓 Your <strong style="color:#4f8ef7">Roll Number</strong> will be auto-generated on registration
        (e.g. <code style="color:#4f8ef7">STU-<?= date('Y') ?>-0001</code>).
        The admin can then add your subject marks from the dashboard.
      </div>

      <!-- Password -->
      <div class="form-group mb-16">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="Min 8 chars, 1 uppercase, 1 number" required>
        <div class="password-strength mt-8" id="strengthBar" style="display:none">
          <div class="progress"><div class="progress-bar" id="strengthFill" style="width:0%"></div></div>
          <small id="strengthLabel" class="text-muted" style="font-size:11px"></small>
        </div>
      </div>

      <!-- Confirm Password -->
      <div class="form-group mb-24">
        <label for="confirm">Confirm Password</label>
        <input type="password" id="confirm" name="confirm" class="form-control"
               placeholder="Re-enter your password" required>
        <small id="matchMsg" class="form-error" style="display:none">Passwords do not match.</small>
      </div>

      <button type="submit" class="btn btn-primary w-100" id="regBtn">
        Register &amp; Get Roll Number →
      </button>
    </form>

    <p class="auth-link">Already have an account? <a href="/ajim/auth/login.php">Sign in</a></p>
  </div>
</div>

<script>
document.querySelectorAll('.alert[data-dismiss]').forEach(el => setTimeout(() => el.remove(), 5000));

const pwInput  = document.getElementById('password');
const cfInput  = document.getElementById('confirm');
const bar      = document.getElementById('strengthFill');
const label    = document.getElementById('strengthLabel');
const matchMsg = document.getElementById('matchMsg');

pwInput.addEventListener('input', function() {
  const v = this.value;
  document.getElementById('strengthBar').style.display = v ? 'block' : 'none';
  let score = 0;
  if (v.length >= 8) score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;

  const pct = (score / 4) * 100;
  bar.style.width = pct + '%';
  bar.style.background = pct <= 25 ? '#f85149' : pct <= 50 ? '#d29922' : pct <= 75 ? '#4f8ef7' : '#3fb950';
  label.textContent = ['Very Weak','Weak','Fair','Strong','Very Strong'][score];
  label.style.color  = pct <= 25 ? '#f85149' : pct <= 50 ? '#d29922' : pct <= 75 ? '#4f8ef7' : '#3fb950';
});

cfInput.addEventListener('input', function() {
  matchMsg.style.display = (this.value && this.value !== pwInput.value) ? 'block' : 'none';
});

document.getElementById('regForm')?.addEventListener('submit', function() {
  document.getElementById('regBtn').textContent = 'Creating account…';
});
</script>
</body>
</html>
