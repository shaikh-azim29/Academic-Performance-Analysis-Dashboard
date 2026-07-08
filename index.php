<?php
require_once __DIR__ . '/config/session.php';
requireLogin();
header('Location: /ajim/dashboard.php');
exit;
