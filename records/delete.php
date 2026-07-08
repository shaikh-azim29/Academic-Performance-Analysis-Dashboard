<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
requireAdminOrTeacher(); // Only admins/teachers can delete records

$id     = (int)($_GET['id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if (!$id) { header('Location: /ajim/records/index.php'); exit; }

if (isAdmin()) {
    $stmt = $conn->prepare('DELETE FROM records WHERE id = ?');
    $stmt->bind_param('i', $id);
} else {
    $stmt = $conn->prepare('DELETE FROM records WHERE id = ? AND added_by = ?');
    $stmt->bind_param('ii', $id, $userId);
}

$stmt->execute();
setFlash($stmt->affected_rows > 0 ? 'success' : 'danger',
         $stmt->affected_rows > 0 ? 'Record deleted.' : 'Delete failed.');
$stmt->close();
header('Location: /ajim/records/index.php');
exit;
