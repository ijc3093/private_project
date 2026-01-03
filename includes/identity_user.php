<?php
// /Business_only3/includes/identity_user.php
require_once __DIR__ . '/session_user.php';

function userId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function userEmail(): string {
    return trim($_SESSION['user_login'] ?? '');
}

function userName(): string {
    return trim($_SESSION['user_name'] ?? '');
}

function userRoleId(): int {
    // IMPORTANT: session_user.php must store $_SESSION['user_role']
    return (int)($_SESSION['user_role'] ?? 0);
}

function isEmail(string $s): bool {
    return (strpos($s, '@') !== false) && filter_var($s, FILTER_VALIDATE_EMAIL);
}

function isFriendCode(string $s): bool {
    // Example accepted: ABCD-EFGH-IJKL (or any letters/numbers with dashes)
    $s = trim($s);
    return (bool)preg_match('/^[A-Za-z0-9]{3,6}(-[A-Za-z0-9]{3,6}){1,4}$/', $s);
}
