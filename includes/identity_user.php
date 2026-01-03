<?php
// /Business_only3/includes/user_identity.php
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
