<?php
/**
 * ==========================================================
 * ADMIN SESSION HANDLER
 * File: /Business_only3/admin/includes/session_admin.php
 * ==========================================================
 * - Uses its own session cookie (BUSINESS_ONLY_ADMIN)
 * - Separates admin roles from public users
 * - Prevents cache/back-button access after logout
 * - Enforces role-based access
 * - ✅ Enforces forced password change
 * - ✅ Locks inactive accounts automatically
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
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");
}

// ----------------------------------------------------------
// CLEAR ADMIN SESSION (LOGOUT)
// ----------------------------------------------------------
// function clearAdminSession(): void
// {
//     sendNoCacheHeaders();

//     $_SESSION = [];

//     if (ini_get("session.use_cookies")) {
//         $params = session_get_cookie_params();
//         setcookie(
//             session_name(),
//             '',
//             time() - 42000,
//             $params['path'] ?? '/',
//             $params['domain'] ?? '',
//             (bool)($params['secure'] ?? false),
//             true // httponly
//         );
//     }

//     session_destroy();
// }

function clearAdminSession(): void
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Small helper: current script filename
 */
function currentAdminScript(): string
{
    return basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
}

/**
 * ✅ Load current admin row (status/force lock)
 */
function fetchAdminSessionRow(PDO $dbh, int $adminId): ?array
{
    $st = $dbh->prepare("
        SELECT idadmin, username, role, status,
               COALESCE(force_password_change,0) AS force_password_change,
               COALESCE(locked_until,'') AS locked_until
        FROM admin
        WHERE idadmin = :id
        LIMIT 1
    ");
    $st->execute([':id' => $adminId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// ----------------------------------------------------------
// REQUIRE ADMIN LOGIN
// ----------------------------------------------------------
function requireAdminLogin(): void
{
    sendNoCacheHeaders();

    // must have these session keys
    if (empty($_SESSION['admin_login']) || empty($_SESSION['admin_id']) || empty($_SESSION['userRole'])) {
        header("Location: index.php");
        exit;
    }

    $role = (int)$_SESSION['userRole'];
    $allowed = [1, 2, 3, 4]; // Admin, Manager, Gospel, Staff

    if (!in_array($role, $allowed, true)) {
        clearAdminSession();
        header("Location: index.php");
        exit;
    }

    // ✅ Pull live account status from DB
    $adminId = (int)$_SESSION['admin_id'];
    if ($adminId <= 0) {
        clearAdminSession();
        header("Location: index.php");
        exit;
    }

    // Load DB safely
    require_once __DIR__ . '/../controller.php';
    $controller = new Controller();
    $dbh = $controller->pdo();

    $row = fetchAdminSessionRow($dbh, $adminId);
    if (!$row) {
        // account deleted
        clearAdminSession();
        header("Location: index.php");
        exit;
    }

    // ✅ Lock inactive accounts automatically
    if ((int)$row['status'] !== 1) {
        clearAdminSession();
        header("Location: index.php?inactive=1");
        exit;
    }

    // ✅ If temporary lock is active, log them out (or you can redirect to index)
    if (!empty($row['locked_until']) && strtotime($row['locked_until']) > time()) {
        clearAdminSession();
        header("Location: index.php?locked=1");
        exit;
    }

    // ✅ Forced password change: allow only change-password.php & logout.php & index.php
    $script = currentAdminScript();
    $allowedDuringForce = ['change-password.php', 'logout.php', 'index.php'];

    if ((int)$row['force_password_change'] === 1 && !in_array($script, $allowedDuringForce, true)) {
        header("Location: change-password.php?force=1");
        exit;
    }
}


// ----------------------------------------------------------
// SET ADMIN SESSION (LOGIN SUCCESS)
// ----------------------------------------------------------
function setAdminSession(array $admin): void
{
    session_regenerate_id(true);

    $_SESSION['admin_login'] = (string)($admin['username'] ?? '');
    $_SESSION['admin_id']    = (int)($admin['idadmin'] ?? 0);
    $_SESSION['userRole']    = (int)($admin['role'] ?? 0);

    // optional / convenience
    $_SESSION['admin_email']       = (string)($admin['email'] ?? '');
    $_SESSION['admin_image']       = (string)($admin['image'] ?? 'default.jpg');
    $_SESSION['admin_friend_code'] = (string)($admin['friend_code'] ?? '');
}
