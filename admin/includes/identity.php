<?php
require_once __DIR__ . '/session_admin.php';
requireAdminLogin();

function myRoleId(): int {
    return (int)($_SESSION['userRole'] ?? 0);
}
function myUsername(): string {
    return trim($_SESSION['admin_login'] ?? '');
}
function myAdminId(): int {
    return (int)($_SESSION['admin_id'] ?? 0);
}
function myAdminFriendCode(): string {
    return trim($_SESSION['admin_friend_code'] ?? '');
}
function isAdmin(): bool {
    return myRoleId() === 1;
}

/**
 * All internal chat channels this role can receive
 */
// function allowedInternalChannelsForMe(): array {
//     $role = myRoleId();

//     if ($role === 1) return ['admin_manager','admin_staff','admin_admin']; // Admin internal
//     if ($role === 2) return ['admin_manager','manager_manager','manager_staff']; // Manager
//     if ($role === 4) return ['admin_staff','staff_staff','manager_staff']; // Staff

//     return [];
// }

function allowedInternalChannelsForMe(): array {
    $role = myRoleId();

    if ($role === 1) return ['admin_manager','admin_staff','admin_admin','manager_staff','manager_manager','staff_staff'];
    if ($role === 2) return ['admin_manager','manager_manager','manager_staff'];
    if ($role === 4) return ['admin_staff','staff_staff','manager_staff'];

    return [];
}


/**
 * Determine chat channel from roles
 */
function channelForAdminRoles(int $myRole, int $peerRole): string
{
    // Admin <-> Manager
    if (($myRole === 1 && $peerRole === 2) || ($myRole === 2 && $peerRole === 1)) return 'admin_manager';

    // Admin <-> Staff
    if (($myRole === 1 && $peerRole === 4) || ($myRole === 4 && $peerRole === 1)) return 'admin_staff';

    // Admin <-> Admin
    if ($myRole === 1 && $peerRole === 1) return 'admin_admin';

    // Manager <-> Manager
    if ($myRole === 2 && $peerRole === 2) return 'manager_manager';

    // Staff <-> Staff
    if ($myRole === 4 && $peerRole === 4) return 'staff_staff';

    // ✅ Manager <-> Staff
    if (($myRole === 2 && $peerRole === 4) || ($myRole === 4 && $peerRole === 2)) return 'manager_staff';
    

    return '';
}


/**
 * Create XXXX-XXXX-XXXX
 */
function generateFriendCode(int $groups = 3): string {
    $parts = [];
    for ($i=0; $i<$groups; $i++) {
        $parts[] = strtoupper(bin2hex(random_bytes(2))); // 4 chars
    }
    return implode('-', $parts);
}

/**
 * Ensure current admin has a friend_code; generate one if missing.
 * Call this from pages that need it (contacts/add_contact/compose).
 */
function ensureMyAdminFriendCode(PDO $dbh): string
{
    $meId = myAdminId();
    if ($meId <= 0) return '';

    $st = $dbh->prepare("SELECT friend_code FROM admin WHERE idadmin = :id LIMIT 1");
    $st->execute([':id' => $meId]);
    $code = (string)($st->fetchColumn() ?: '');

    if ($code !== '') {
        $_SESSION['admin_friend_code'] = $code;
        return $code;
    }

    for ($i=0; $i<30; $i++) {
        $new = generateFriendCode();
        $dup = $dbh->prepare("SELECT idadmin FROM admin WHERE friend_code = :c LIMIT 1");
        $dup->execute([':c' => $new]);
        if (!$dup->fetchColumn()) {
            $upd = $dbh->prepare("UPDATE admin SET friend_code = :c WHERE idadmin = :id LIMIT 1");
            $upd->execute([':c' => $new, ':id' => $meId]);
            $_SESSION['admin_friend_code'] = $new;
            return $new;
        }
    }

    return '';
}

/**
 * Contact lock: must exist to chat
 */
function isInAdminContacts(PDO $dbh, int $ownerAdminId, int $friendAdminId): bool {
    $st = $dbh->prepare("
        SELECT id
        FROM admin_contacts
        WHERE owner_admin_id = :o
          AND friend_admin_id = :f
        LIMIT 1
    ");
    $st->execute([':o' => $ownerAdminId, ':f' => $friendAdminId]);
    return (bool)$st->fetchColumn();
}


/**
 * Which "notireceiver" values belong to me in notification table?
 *
 * Your notification table uses strings like:
 * - 'Admin' for user->admin notifications
 * - admin usernames for internal notifications
 * - sometimes you may also want email (optional)
 */
// function myNotificationReceiverKeys(): array
// {
//     $keys = [];

//     // Internal notifications for admin/staff/manager accounts:
//     $u = myUsername();
//     if ($u !== '') $keys[] = $u;

//     // Support-center notifications for Admin only
//     if (isAdmin()) $keys[] = 'Admin';

//     // IMPORTANT:
//     // Do NOT include admin_email here,
//     // because public user-to-user notifications are addressed to emails.
//     // This prevents "friend sends friend" notifications from appearing in admin.

//     return array_values(array_unique(array_filter($keys)));
// }

/**
 * Role-based notification receiver keys.
 * notification.notireceiver should store role labels:
 *  Admin / Manager / Gospel / Staff
 */
function myNotificationReceiverKeys(): array
{
    $role = myRoleId(); // 1 Admin, 2 Manager, 3 Gospel, 4 Staff

    if ($role === 1) return ['Admin', 'Manager', 'Gospel', 'Staff']; // Admin sees all
    if ($role === 2) return ['Manager'];                             // Manager only
    if ($role === 3) return ['Gospel'];                              // Gospel only
    if ($role === 4) return ['Staff'];                               // Staff only

    return [];
}


/**
 * Determine if the notireceiver belongs to this logged-in admin account.
 */
function isMyNotificationReceiver(string $receiver): bool
{
    $receiver = trim($receiver);
    if ($receiver === '') return false;
    return in_array($receiver, myNotificationReceiverKeys(), true);
}


/**
 * Generate friend code like XXXX-XXXX-XXXX
 */
function generateAdminFriendCode(): string {
    return strtoupper(
        substr(bin2hex(random_bytes(2)), 0, 4) . '-' .
        substr(bin2hex(random_bytes(2)), 0, 4) . '-' .
        substr(bin2hex(random_bytes(2)), 0, 4)
    );
}

/**
 * Ensure admin has friend code
 */
function ensureAdminFriendCode(PDO $dbh): string {
    $adminId = myAdminId();
    if ($adminId <= 0) return '';

    // already exists?
    $st = $dbh->prepare("SELECT friend_code FROM admin WHERE idadmin = :id LIMIT 1");
    $st->execute([':id' => $adminId]);
    $code = trim((string)$st->fetchColumn());

    if ($code !== '') {
        $_SESSION['admin_friend_code'] = $code;
        return $code;
    }

    // generate unique
    for ($i = 0; $i < 20; $i++) {
        $new = generateAdminFriendCode();
        $chk = $dbh->prepare("SELECT idadmin FROM admin WHERE friend_code = :c LIMIT 1");
        $chk->execute([':c' => $new]);

        if (!$chk->fetchColumn()) {
            $upd = $dbh->prepare("UPDATE admin SET friend_code = :c WHERE idadmin = :id LIMIT 1");
            $upd->execute([':c' => $new, ':id' => $adminId]);

            $_SESSION['admin_friend_code'] = $new;
            return $new;
        }
    }

    return '';
}

