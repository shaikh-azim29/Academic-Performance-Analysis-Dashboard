<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * A "student" user is any logged-in user with role = 'student'.
 * They are linked to a specific student record via $_SESSION['student_id'].
 */
function isStudent(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /ajim/auth/login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /ajim/dashboard.php?error=unauthorized');
        exit;
    }
}

/**
 * Blocks student role from accessing admin/teacher-only pages.
 * Redirects students to the dashboard with an access-denied message.
 */
function requireAdminOrTeacher(): void {
    requireLogin();
    if (isStudent()) {
        header('Location: /ajim/dashboard.php?error=unauthorized');
        exit;
    }
}

function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
?>
