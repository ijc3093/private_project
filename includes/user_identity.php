<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../admin/controller.php';

/**
 * Internal cache for the current logged-in user row.
 */
function _currentUserRow(PDO $dbh): array {
    static $cached = null;
    if (is_array($cached)) return $cached;

    $username = (string)($_SESSION['user_login'] ?? '');
    $username = trim($username);

    if ($username === '') {
        $cached = [];
        return $cached;
    }

    // Your app logs in using username stored in session_user.php
    $st = $dbh->prepare("
        SELECT id, name, username, friend_code, email, role, status
        FROM users
        WHERE username = :u
        LIMIT 1
    ");
    $st->execute([':u' => $username]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    $cached = is_array($row) ? $row : [];
    return $cached;
}

/** Get a PDO handle safely */
function _identityPdo(): PDO {
    $controller = new Controller();
    return $controller->pdo();
}

/** Session username */
// function userUsername(): string {
//     return trim((string)($_SESSION['user_login'] ?? ''));
// }

/** Users.id */
function userId(): int {
    try {
        $dbh = _identityPdo();
        $u = _currentUserRow($dbh);
        return (int)($u['id'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/** Users.email */
// function userEmail(): string {
//     try {
//         $dbh = _identityPdo();
//         $u = _currentUserRow($dbh);
//         return trim((string)($u['email'] ?? ''));
//     } catch (Throwable $e) {
//         return '';
//     }
// }

/** Users.friend_code */
// function userFriendCode(): string {
//     try {
//         $dbh = _identityPdo();
//         $u = _currentUserRow($dbh);
//         return trim((string)($u['friend_code'] ?? ''));
//     } catch (Throwable $e) {
//         return '';
//     }
// }

/** Users.role */
// function userRoleId(): int {
//     try {
//         $dbh = _identityPdo();
//         $u = _currentUserRow($dbh);
//         return (int)($u['role'] ?? 0);
//     } catch (Throwable $e) {
//         return 0;
//     }
// }

/** Users.status */
function userStatus(): int {
    try {
        $dbh = _identityPdo();
        $u = _currentUserRow($dbh);
        return (int)($u['status'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}
