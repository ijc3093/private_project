<?php
// /Business_only3/admin/includes/session_admin.php
declare(strict_types=1);

require_once __DIR__ . '/../controller.php';

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
function clearAdminSession(): void
{
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
            true
        );
    }
    session_destroy();
}

function currentAdminScript(): string
{
    return basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
}

function adminDbh(): PDO
{
    static $dbh = null;
    if ($dbh instanceof PDO) return $dbh;

    $controller = new Controller();
    $dbh = $controller->pdo();
    return $dbh;
}

/**
 * Check role exists in `role` table (old system)
 * role table columns: idrole, name
 */
function roleExists(PDO $dbh, int $roleId): bool
{
    if ($roleId <= 0) return false;
    $st = $dbh->prepare("SELECT idrole FROM role WHERE idrole = :id LIMIT 1");
    $st->execute([':id' => $roleId]);
    return (bool)$st->fetchColumn();
}

function fetchAdminSessionRow(PDO $dbh, int $adminId): ?array
{
    $st = $dbh->prepare("
        SELECT idadmin, username, email, role, status,
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

/**
 * Where to send user after login
 * (keep simple: everyone uses dashboard.php)
 */
function dashboardForRole(int $roleId): string
{
    return 'dashboard.php';
}

// ----------------------------------------------------------
// REQUIRE ADMIN LOGIN
// ----------------------------------------------------------
function requireAdminLogin(): void
{
    sendNoCacheHeaders();

    if (empty($_SESSION['admin_id']) || empty($_SESSION['userRole'])) {
        header("Location: index.php");
        exit;
    }

    $adminId = (int)$_SESSION['admin_id'];
    $roleId  = (int)$_SESSION['userRole'];

    if ($adminId <= 0 || $roleId <= 0) {
        clearAdminSession();
        header("Location: index.php");
        exit;
    }

    $dbh = adminDbh();

    // ✅ role must exist in `role` table (old system)
    if (!roleExists($dbh, $roleId)) {
        clearAdminSession();
        header("Location: index.php?inactiveRole=1");
        exit;
    }

    $row = fetchAdminSessionRow($dbh, $adminId);
    if (!$row) {
        clearAdminSession();
        header("Location: index.php");
        exit;
    }

    // account must be active
    if ((int)$row['status'] !== 1) {
        clearAdminSession();
        header("Location: index.php?inactive=1");
        exit;
    }

    // account lockout
    if (!empty($row['locked_until']) && strtotime((string)$row['locked_until']) > time()) {
        clearAdminSession();
        header("Location: index.php?locked=1");
        exit;
    }

    // keep session synced
    $_SESSION['admin_login'] = (string)$row['username'];
    $_SESSION['admin_email'] = (string)$row['email'];
    $_SESSION['userRole']    = (int)$row['role'];

    // force password change
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

    $_SESSION['admin_id']    = (int)($admin['idadmin'] ?? 0);
    $_SESSION['admin_login'] = (string)($admin['username'] ?? '');
    $_SESSION['admin_email'] = (string)($admin['email'] ?? '');
    $_SESSION['userRole']    = (int)($admin['role'] ?? 0);

    $_SESSION['admin_image']       = (string)($admin['image'] ?? 'default.jpg');
    $_SESSION['admin_friend_code'] = (string)($admin['friend_code'] ?? '');
}
