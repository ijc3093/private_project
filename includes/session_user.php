<?php
// /Business_only3/includes/session_user.php

if (session_status() === PHP_SESSION_NONE) {
    session_name('BUSINESS_ONLY_USER');
    session_start();
}

function sendNoCacheHeadersUser(): void
{
    if (headers_sent()) return;
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");
}


function requireUserLogin(): void
{
    sendNoCacheHeadersUser();
    if (empty($_SESSION['user_login'])) {
        header("Location: index.php");
        exit;
    }
}

function setUserSession(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_login'] = trim($user['email'] ?? '');
    $_SESSION['user_id']    = (int)($user['id'] ?? 0);
    $_SESSION['user_name']  = (string)($user['name'] ?? '');
    $_SESSION['user_image'] = (string)($user['image'] ?? 'default.jpg');

    // ✅ IMPORTANT: consistent key
    $_SESSION['user_role']  = (int)($user['role'] ?? 0);
    $_SESSION['user_status']= (int)($user['status'] ?? 1);
}

function clearUserSession(): void
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
}

// ✅ helpers used by sendreply_user.php
function myUserId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function myUserEmail(): string
{
    return trim($_SESSION['user_login'] ?? '');
}

function myUserName(): string
{
    return trim($_SESSION['user_name'] ?? '');
}

function myUserRoleId(): int
{
    // ✅ FIXED: use user_role
    return (int)($_SESSION['user_role'] ?? 0);
}
