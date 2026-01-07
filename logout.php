<?php
require_once __DIR__ . '/includes/session_user.php';
require_once __DIR__ . '/admin/controller.php';

$controller = new Controller();
$username = (string)($_SESSION['user_name'] ?? '');
$controller->logSecurity('user_logout', true, myUserEmail(), $username);

clearUserSession();
header("Location: index.php");
exit;
