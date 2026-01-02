<?php
// /Business_only3/includes/session_user.php
// USER SESSION HANDLER (PUBLIC USERS)

if (session_status() === PHP_SESSION_NONE) {
    // ✅ Different cookie name than admin session
    session_name('BUSINESS_ONLY_USER');
    session_start();
}

/**
 * Optional: stop cached pages after logout (recommended)
 */
function sendNoCacheHeadersUser(): void
{
    if (headers_sent()) return;

    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");
}

/**
 * Require user login
 */
function requireUserLogin(): void
{
    sendNoCacheHeadersUser();

    if (
        empty($_SESSION['user_login']) ||
        empty($_SESSION['user_id'])
    ) {
        header("Location: index.php");
        exit;
    }
}

/**
 * Set user session after login/register
 * IMPORTANT: include role for "user ↔ user same role" feature.
 *
 * Expected $user keys:
 * - id, email, name, image, role (optional but recommended), status (optional)
 */
function setUserSession(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_login'] = $user['email'] ?? '';          // user email
    $_SESSION['user_id']    = (int)($user['id'] ?? 0);
    $_SESSION['user_name']  = $user['name'] ?? '';
    $_SESSION['user_image'] = $user['image'] ?? 'default.jpg';

    // ✅ NEW: store user role (needed for same-role chat)
    // If your users table doesn't have role yet, add it (INT DEFAULT 4 or whatever you want).
    //$_SESSION['userRole']   = (int)($user['role'] ?? 0);
    $_SESSION['userRole'] = (int)($user['role'] ?? 4);


    // Optional: store status if you want to block inactive users
    $_SESSION['user_status'] = (int)($user['status'] ?? 1);
}

/**
 * Clear session on logout
 */
function clearUserSession(): void
{
    sendNoCacheHeadersUser();

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Helpers (optional, but very useful)
 */
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
    return (int)($_SESSION['userRole'] ?? 0);
}
