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

    // username-based session identity
    if (empty($_SESSION['user_login']) || empty($_SESSION['user_id'])) {
        header("Location: index.php?session=reset");
        exit;
    }
}

function setUserSession(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_id']          = (int)($user['id'] ?? 0);

    // ✅ username is the login identity
    $_SESSION['user_login']       = trim((string)($user['username'] ?? ''));

    // ✅ keep email too
    $_SESSION['user_email']       = trim((string)($user['email'] ?? ''));

    // ✅ friend_code is the canonical chat identity
    $_SESSION['user_friend_code'] = trim((string)($user['friend_code'] ?? ''));

    $_SESSION['user_name']   = (string)($user['name'] ?? '');
    $_SESSION['user_image']  = (string)($user['image'] ?? 'default.jpg');
    $_SESSION['user_role']   = (int)($user['role'] ?? 0);
    $_SESSION['user_status'] = (int)($user['status'] ?? 1);
}

function clearUserSession(): void
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"], $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}

/** Helpers */
function myUserId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function userUsername(): string
{
    return trim((string)($_SESSION['user_login'] ?? ''));
}

function userEmail(): string
{
    return trim((string)($_SESSION['user_email'] ?? ''));
}

/** Backward compatible name (older code may call this) */
function myUserEmail(): string
{
    return userEmail();
}

function userFriendCode(): string
{
    return trim((string)($_SESSION['user_friend_code'] ?? ''));
}

function userRoleId(): int
{
    return (int)($_SESSION['user_role'] ?? 0);
}

function myUserName(): string
{
    return trim((string)($_SESSION['user_name'] ?? ''));
}


function myUserRoleId(): int
{
    return (int)($_SESSION['user_role'] ?? 0);
}