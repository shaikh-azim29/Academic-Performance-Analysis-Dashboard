<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
requireLogin();

$activePage = 'dashboard';
$pageTitle  = 'Dashboard';

$userId   = (int)$_SESSION['user_id'];
$isAdmin  = isAdmin();
$isStudent = isStudent();
$myStudentId = $isStudent ? (int)($_SESSION['student_id'] ?? 0) : 0;

// ── Stats ──────────────────────────────────────────────────────
if ($isStudent) {
    $statsQuery = $myStudentId
        ? 'SELECT COUNT(DISTINCT student_id) FROM records WHERE student_id = ?'
        : 'SELECT 0';
} else {
    $statsQuery = 'SELECT COUNT(*) FROM students';
}
$stmt = $conn->prepare($statsQuery);
if ($isStudent && $myStudentId) { $stmt->bind_param('i', $myStudentId); }
$stmt->execute();
$stmt->bind_result($totalStudents); $stmt->fetch(); $stmt->close();
// For students, show their own record count
$recQ = $isStudent
    ? ($myStudentId ? 'SELECT COUNT(*) FROM records WHERE student_id = ?' : 'SELECT 0')
    : 'SELECT COUNT(*) FROM records';
$stmt = $conn->prepare($recQ);
if ($isStudent && $myStudentId) { $stmt->bind_param('i', $myStudentId); }
$stmt->execute();
$stmt->bind_result($totalRecords); $stmt->fetch(); $stmt->close();

// Average percentage
$avgQ = $isStudent
    ? ($myStudentId ? 'SELECT ROUND(AVG(marks/max_marks*100),1) FROM records WHERE student_id = ?' : 'SELECT 0')
    : 'SELECT ROUND(AVG(marks/max_marks*100),1) FROM records';
$stmt = $conn->prepare($avgQ);
if ($isStudent && $myStudentId) { $stmt->bind_param('i', $myStudentId); }
$stmt->execute();
$stmt->bind_result($avgPct); $stmt->fetch(); $stmt->close();
$avgPct = $avgPct ?? 0;

// Total reports
$repQ = $isStudent
    ? ($myStudentId ? 'SELECT COUNT(*) FROM reports WHERE student_id = ?' : 'SELECT 0')
    : 'SELECT COUNT(*) FROM reports';
$stmt = $conn->prepare($repQ);
if ($isStudent && $myStudentId) { $stmt->bind_param('i', $myStudentId); }
$stmt->execute();
$stmt->bind_result($totalReports); $stmt->fetch(); $stmt->close();

// ── Subject-wise average (for bar chart) ──────────────────────
if ($isStudent) {
    $chartQ = $myStudentId
        ? 'SELECT subject, ROUND(AVG(marks/max_marks*100),1) AS avg_pct
           FROM records WHERE student_id = ? GROUP BY subject ORDER BY avg_pct DESC LIMIT 8'
        : 'SELECT NULL, NULL WHERE 1=0';
} else {
    $chartQ = 'SELECT subject, ROUND(AVG(marks/max_marks*100),1) AS avg_pct
       FROM records GROUP BY subject ORDER BY avg_pct DESC LIMIT 8';
}
$stmt = $conn->prepare($chartQ);
if ($isStudent && $myStudentId) { $stmt->bind_param('i', $myStudentId); }
$stmt->execute();
$chartResult = $stmt->get_result();
$chartLabels = []; $chartData = [];
while ($row = $chartResult->fetch_assoc()) {
    $chartLabels[] = $row['subject'];
    $chartData[]   = (float)$row['avg_pct'];
}
$stmt->close();

// ── Performance distribution (for doughnut) ───────────────────
$distBase = $isStudent && $myStudentId ? 'WHERE student_id = ?' : ($isStudent ? 'WHERE 1=0' : '');
$distQ = "SELECT
    SUM(CASE WHEN marks/max_marks*100 >= 75 THEN 1 ELSE 0 END) AS excellent,
    SUM(CASE WHEN marks/max_marks*100 >= 60 AND marks/max_marks*100 < 75 THEN 1 ELSE 0 END) AS good,
    SUM(CASE WHEN marks/max_marks*100 >= 45 AND marks/max_marks*100 < 60 THEN 1 ELSE 0 END) AS average,
    SUM(CASE WHEN marks/max_marks*100 < 45 THEN 1 ELSE 0 END) AS below
   FROM records $distBase";
$stmt = $conn->prepare($distQ);
if ($isStudent && $myStudentId) { $stmt->bind_param('i', $myStudentId); }
$stmt->execute();
$dist = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Recent records ─────────────────────────────────────────────
if ($isStudent) {
    $recentQ = $myStudentId
        ? 'SELECT r.*, s.name AS student_name, s.student_code
           FROM records r
           JOIN students s ON s.id = r.student_id
           WHERE r.student_id = ?
           ORDER BY r.created_at DESC LIMIT 8'
        : 'SELECT NULL WHERE 1=0';
} else {
    $recentQ = 'SELECT r.*, s.name AS student_name, s.student_code
       FROM records r
       JOIN students s ON s.id = r.student_id
       ORDER BY r.created_at DESC LIMIT 8';
}
$stmt = $conn->prepare($recentQ);
if ($isStudent && $myStudentId) { $stmt->bind_param('i', $myStudentId); }
$stmt->execute();
$recentRecords = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function gradeBadge(float $pct): array {
    if ($pct >= 90) return ['A+', 'badge-green'];
    if ($pct >= 75) return ['A',  'badge-green'];
    if ($pct >= 60) return ['B',  'badge-blue'];
    if ($pct >= 45) return ['C',  'badge-yellow'];
    if ($pct >= 33) return ['D',  'badge-yellow'];
    return ['F', 'badge-red'];
}

include __DIR__ . '/includes/header.php';
?>

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>" data-dismiss>
  <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

<!-- ── Stat Cards ─────────────────────────────────────────── -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon blue">🎓</div>
    <div class="stat-value text-accent"><?= number_format($totalStudents) ?></div>
    <div class="stat-label"><?= $isStudent ? 'Subjects Taken' : 'Total Students' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">📝</div>
    <div class="stat-value text-green"><?= number_format($totalRecords) ?></div>
    <div class="stat-label">Performance Records</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow">📊</div>
    <div class="stat-value text-yellow"><?= $avgPct ?>%</div>
    <div class="stat-label">Average Score</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">📋</div>
    <div class="stat-value text-purple"><?= number_format($totalReports) ?></div>
    <div class="stat-label">Reports Generated</div>
  </div>
</div>

<!-- ── Charts ─────────────────────────────────────────────── -->
<div class="chart-grid">
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Subject-wise Average Score</div>
        <div class="card-subtitle">Performance % by subject</div>
      </div>
    </div>
    <div class="chart-canvas-wrap">
      <canvas id="barChart"></canvas>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Performance Distribution</div>
        <div class="card-subtitle">Breakdown of all records</div>
      </div>
    </div>
    <div class="chart-canvas-wrap">
      <canvas id="doughnutChart"></canvas>
    </div>
  </div>
</div>

<!-- ── Recent Records ─────────────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">Recent Records</div>
      <div class="card-subtitle">Latest performance entries</div>
    </div>
    <?php if (!$isStudent): ?>
    <a href="/ajim/records/index.php" class="btn btn-outline btn-sm">View All</a>
    <?php endif; ?>
  </div>

  <?php if (empty($recentRecords)): ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <p><?= $isStudent ? 'No records yet for your account.' : 'No records yet. <a href="/ajim/records/create.php">Add the first one!</a>' ?></p>
    </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Subject</th>
          <th>Marks</th>
          <th>Percentage</th>
          <th>Grade</th>
          <th>Type</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentRecords as $r):
          $pct = round($r['marks'] / $r['max_marks'] * 100, 1);
          [$grade, $badgeClass] = gradeBadge($pct);
        ?>
        <tr>
          <td>
            <div class="fw-600"><?= htmlspecialchars($r['student_name']) ?></div>
            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($r['student_code']) ?></div>
          </td>
          <td><?= htmlspecialchars($r['subject']) ?></td>
          <td class="table-num"><?= $r['marks'] ?> / <?= $r['max_marks'] ?></td>
          <td>
            <div class="d-flex align-center gap-8">
              <div class="progress flex-1"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
              <span class="table-num" style="font-size:12px"><?= $pct ?>%</span>
            </div>
          </td>
          <td><span class="badge <?= $badgeClass ?>"><?= $grade ?></span></td>
          <td><span class="badge badge-blue"><?= ucfirst($r['exam_type']) ?></span></td>
          <td class="text-secondary"><?= date('M j, Y', strtotime($r['exam_date'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
const CHART_DEFAULTS = {
  color: '#8b949e',
  font: { family: "'Inter', sans-serif", size: 12 }
};
Chart.defaults.color = CHART_DEFAULTS.color;
Chart.defaults.font  = CHART_DEFAULTS.font;

// Bar Chart
new Chart(document.getElementById('barChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{
      label: 'Avg %',
      data: <?= json_encode($chartData) ?>,
      backgroundColor: 'rgba(79,142,247,.7)',
      borderColor: '#4f8ef7',
      borderWidth: 1,
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, max: 100, grid: { color: 'rgba(255,255,255,.05)' } },
      x: { grid: { display: false } }
    }
  }
});

// Doughnut Chart
new Chart(document.getElementById('doughnutChart'), {
  type: 'doughnut',
  data: {
    labels: ['Excellent (≥75%)', 'Good (60–74%)', 'Average (45–59%)', 'Below (<45%)'],
    datasets: [{
      data: [
        <?= (int)($dist['excellent'] ?? 0) ?>,
        <?= (int)($dist['good']      ?? 0) ?>,
        <?= (int)($dist['average']   ?? 0) ?>,
        <?= (int)($dist['below']     ?? 0) ?>
      ],
      backgroundColor: ['#3fb950','#4f8ef7','#d29922','#f85149'],
      borderWidth: 0,
      hoverOffset: 8,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true } }
    },
    cutout: '65%'
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
