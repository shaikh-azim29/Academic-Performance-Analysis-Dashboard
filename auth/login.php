<?php
require_once __DIR__ . '/../config/session.php';

if (isLoggedIn()) {
    header('Location: /ajim/dashboard.php');
    exit;
}

$activePage = 'home';
$pageTitle  = 'Welcome';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/db.php';
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 1) {
        $errors[] = 'Password is required.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT id, name, email, password, role, student_id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['name']       = $user['name'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['student_id'] = $user['student_id']; // null for admins
            setFlash('success', 'Welcome back, ' . $user['name'] . '!');
            header('Location: /ajim/dashboard.php');
            exit;
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Login to Academic Performance Analysis Dashboard">
  <title>Login | APAD</title>
  <link rel="stylesheet" href="/ajim/assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">📊</div>
    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-sub">Sign in to your APAD account</p>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger" data-dismiss>
        ⚠️ <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
      </div>
    <?php endif; ?>

    <?php
      $flash = getFlash();
      if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>" data-dismiss>
        <?= $flash['msg'] /* HTML allowed – roll number shown in bold */ ?>
      </div>
    <?php endif; ?>

    <form method="POST" id="loginForm" novalidate>
      <div class="form-group mb-16">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="you@example.com" required autocomplete="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>

      <div class="form-group mb-24">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="••••••••" required autocomplete="current-password">
      </div>

      <button type="submit" class="btn btn-primary w-100" id="loginBtn">
        Sign In →
      </button>
    </form>

    <p class="auth-link">Don't have an account? <a href="/ajim/auth/register.php">Register here</a></p>
  </div>
</div>

<script>
document.querySelectorAll('.alert[data-dismiss]').forEach(el => {
  setTimeout(() => el.remove(), 5000);
});
document.getElementById('loginForm')?.addEventListener('submit', function() {
  document.getElementById('loginBtn').textContent = 'Signing in…';
});
</script>
</body>
</html>
