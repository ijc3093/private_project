<?php
// /Business_only/includes/session_user.php

if (session_status() === PHP_SESSION_NONE) {
    // ✅ Different cookie name than admin session
    session_name('BUSINESS_ONLY_USER');
    session_start();
}

function requireUserLogin(): void
{
    if (empty($_SESSION['user_login'])) {
        header("Location: index.php");
        exit;
    }
}

function setUserSession(array $user): void
{
    // ✅ Store only user keys here
    $_SESSION['user_login'] = $user['email'];
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['user_name']  = $user['name'] ?? '';
    $_SESSION['user_image'] = $user['image'] ?? 'default.jpg';
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
