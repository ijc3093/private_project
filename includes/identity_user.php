<?php
// /Business_only3/includes/identity_user.php

if (!function_exists('myUserEmail')) {
    function myUserEmail(): string {
        return trim($_SESSION['user_login'] ?? '');
    }
}

if (!function_exists('myUserId')) {
    function myUserId(): int {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}

if (!function_exists('myUserRoleId')) {
    function myUserRoleId(): int {
        return (int)($_SESSION['userRole'] ?? 0);
    }
}

if (!function_exists('adminInboxKey')) {
    function adminInboxKey(): string {
        return 'Admin';
    }
}
