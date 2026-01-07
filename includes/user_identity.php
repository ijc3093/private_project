<?php
// /Business_only3/includes/identity_user.php

// Prevent function redeclare errors
if (!function_exists('h')) {
    function h($s): string {
        return htmlentities((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('userId')) {
    function userId(): int {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}

if (!function_exists('userEmail')) {
    function userEmail(): string {
        return trim((string)($_SESSION['user_login'] ?? ''));
    }
}

if (!function_exists('userName')) {
    function userName(): string {
        return trim((string)($_SESSION['user_name'] ?? ''));
    }
}

if (!function_exists('userRoleId')) {
    function userRoleId(): int {
        // Your session_user.php uses user_role
        return (int)($_SESSION['user_role'] ?? 0);
    }
}

if (!function_exists('isLoggedUser')) {
    function isLoggedUser(): bool {
        return userId() > 0 && userEmail() !== '' && userRoleId() > 0;
    }
}
