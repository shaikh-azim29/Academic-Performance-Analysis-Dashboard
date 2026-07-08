<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
requireAdminOrTeacher(); // Students cannot delete student records

$id     = (int)($_GET['id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if (!$id) { header('Location: /ajim/students/index.php'); exit; }

if (isAdmin()) {
    $stmt = $conn->prepare('DELETE FROM students WHERE id = ?');
    $stmt->bind_param('i', $id);
} else {
    $stmt = $conn->prepare('DELETE FROM students WHERE id = ? AND enrolled_by = ?');
    $stmt->bind_param('ii', $id, $userId);
}

if ($stmt->execute() && $stmt->affected_rows > 0) {
    setFlash('success', 'Student deleted.');
} else {
    setFlash('danger', 'Delete failed or access denied.');
}
$stmt->close();
header('Location: /ajim/students/index.php');
exit;
