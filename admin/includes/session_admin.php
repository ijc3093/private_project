<?php
/**
 * ==========================================================
 * ADMIN SESSION HANDLER
 * File: /Business_only/admin/includes/session_admin.php
 * ==========================================================
 * - Uses its own session cookie (BUSINESS_ONLY_ADMIN)
 * - Separates Admin/Manager/Gospel/Staff from public users
 * - Prevents cache/back-button access after logout
 * - Enforces role-based access
 */

// ----------------------------------------------------------
// START SESSION (ADMIN ONLY)
// ----------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name('BUSINESS_ONLY_ADMIN');
    session_start();
}

// ----------------------------------------------------------
// NO-CACHE HEADERS (SECURITY)
// ----------------------------------------------------------
function sendNoCacheHeaders(): void
{
    if (headers_sent()) return;
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// ----------------------------------------------------------
// CLEAR ADMIN SESSION (LOGOUT)
// ----------------------------------------------------------
if (!function_exists('clearAdminSession')) {
    function clearAdminSession(): void
    {
        sendNoCacheHeaders();

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool)($params['secure'] ?? false),
                (bool)($params['httponly'] ?? true)
            );
        }

        session_destroy();
    }
}

// ----------------------------------------------------------
// REQUIRE ADMIN LOGIN
// ----------------------------------------------------------
function requireAdminLogin(): void
{
    sendNoCacheHeaders();

    if (empty($_SESSION['admin_login']) || empty($_SESSION['admin_id']) || empty($_SESSION['userRole'])) {
        header("Location: index.php");
        exit;
    }

    $role = (int)$_SESSION['userRole'];
    $allowed = [1,2,3,4]; // Admin, Manager, Gospel, Staff

    if (!in_array($role, $allowed, true)) {
        clearAdminSession();
        header("Location: index.php");
        exit;
    }
}

// ----------------------------------------------------------
// SET ADMIN SESSION (LOGIN SUCCESS)
// ----------------------------------------------------------
function setAdminSession(string $username, int $role, int $adminId, string $image = 'default.jpg'): void
{
    session_regenerate_id(true);

    $_SESSION['admin_login'] = $username;     // UNIQUE username
    $_SESSION['admin_id']    = (int)$adminId;
    $_SESSION['userRole']    = (int)$role;
    $_SESSION['admin_image'] = $image ?: 'default.jpg';
}


function clearAdminSession(): void
{
    sendNoCacheHeaders();

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
