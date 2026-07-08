<?php
require_once __DIR__ . '/../config/session.php';
$activePage = $activePage ?? '';
$pageTitle  = $pageTitle  ?? 'Dashboard';
$userName   = $_SESSION['name']  ?? 'User';
$userRole   = $_SESSION['role']  ?? 'user';
$userInitial = strtoupper(substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Academic Performance Analysis Dashboard — Monitor and manage student performance records.">
  <title><?= htmlspecialchars($pageTitle) ?> | APAD</title>
  <link rel="stylesheet" href="/ajim/assets/css/style.css">
  <!-- Chart.js CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <!-- Phosphor Icons -->
  <script src="https://unpkg.com/@phosphor-icons/web@2.1.1/src/index.js" defer></script>
</head>
<body>
<div class="app-wrapper">

  <!-- ======= SIDEBAR ======= -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">📊</div>
      <div class="brand-name"><span>AP</span>AD</div>
    </div>

    <nav class="sidebar-nav">
      <span class="nav-section-label">Main</span>

      <a href="/ajim/index.php"
         class="nav-link <?= $activePage === 'home' ? 'active' : '' ?>">
        <span class="nav-icon">🏠</span> Home
      </a>

      <a href="/ajim/dashboard.php"
         class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
        <span class="nav-icon">📈</span> Dashboard
      </a>

      <?php if (!isStudent()): ?>
      <a href="/ajim/students/index.php"
         class="nav-link <?= $activePage === 'students' ? 'active' : '' ?>">
        <span class="nav-icon">🎓</span> Students
      </a>
      <?php endif; ?>

      <a href="/ajim/records/index.php"
         class="nav-link <?= $activePage === 'records' ? 'active' : '' ?>">
        <span class="nav-icon">📝</span> Records
      </a>

      <a href="/ajim/reports/index.php"
         class="nav-link <?= $activePage === 'reports' ? 'active' : '' ?>">
        <span class="nav-icon">📋</span> Reports
      </a>

      <?php if ($userRole === 'admin'): ?>
      <span class="nav-section-label">Admin</span>

      <a href="/ajim/admin/users.php"
         class="nav-link <?= $activePage === 'admin-users' ? 'active' : '' ?>">
        <span class="nav-icon">👥</span> Manage Users
      </a>

      <a href="/ajim/admin/all_records.php"
         class="nav-link <?= $activePage === 'admin-records' ? 'active' : '' ?>">
        <span class="nav-icon">🗂️</span> All Records
      </a>
      <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
      <div class="user-pill">
        <div class="user-avatar"><?= $userInitial ?></div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars($userName) ?></div>
          <div class="user-role"><?= htmlspecialchars($userRole) ?></div>
        </div>
        <a href="/ajim/auth/logout.php" class="logout-btn" title="Logout">🚪</a>
      </div>
    </div>
  </aside>
  <!-- ======= END SIDEBAR ======= -->

  <div class="main-content">
    <!-- ======= TOP HEADER ======= -->
    <header class="top-header">
      <div class="d-flex align-center gap-12">
        <button class="hamburger" id="hamburger" aria-label="Toggle menu">☰</button>
        <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
      </div>
      <div class="header-actions">
        <?php if (!isStudent()): ?>
        <a href="/ajim/records/create.php" class="btn btn-primary btn-sm">
          ＋ Add Record
        </a>
        <?php endif; ?>
      </div>
    </header>
    <!-- ======= END TOP HEADER ======= -->

    <main class="page-body">
