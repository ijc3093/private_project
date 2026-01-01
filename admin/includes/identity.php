<?php
// /Business_only3/admin/includes/identity.php

// Always read from session keys set by setAdminSession()
function myRoleId(): int {
    return (int)($_SESSION['userRole'] ?? 0);
}

// Avoid fatal redeclare if session_admin.php already defines isAdmin()
if (!function_exists('isAdmin')) {
    function isAdmin(): bool {
        return myRoleId() === 1;
    }
}

function myUsername(): string {
    // you stored username here: $_SESSION['admin_login']
    return trim((string)($_SESSION['admin_login'] ?? ''));
}

/**
 * Notifications receiver keys for current account:
 * - Admin: can see notifications addressed to "Admin" (shared) AND their personal username
 * - Manager/Staff/Gospel: only their username
 */
function myNotificationReceiverKeys(): array {
    $me = myUsername();
    if ($me === '') return [];

    if (isAdmin()) {
        // Admin sees BOTH
        return ['Admin', $me];
    }

    // non-admin sees only their personal receiver key
    return [$me];
}
