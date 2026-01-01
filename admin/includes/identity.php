<?php
// /Business_only3/admin/includes/identity.php

// -----------------------------
// Session identity helpers
// -----------------------------
function myRoleId(): int {
    return (int)($_SESSION['userRole'] ?? 0);
}

function myUsername(): string {
    return trim((string)($_SESSION['admin_login'] ?? ''));
}

function myAdminId(): int {
    return (int)($_SESSION['admin_id'] ?? 0);
}

// -----------------------------
// Role helpers
// -----------------------------
function isAdmin(): bool {
    return myRoleId() === 1;
}

function isManager(): bool {
    return myRoleId() === 2;
}

function isGospel(): bool {
    return myRoleId() === 3;
}

function isStaff(): bool {
    return myRoleId() === 4;
}

// -----------------------------
// Notification receiver keys
// IMPORTANT RULE:
// - Internal notifications use USERNAME (not email)
// - Admin also checks legacy receiver "Admin" (for older rows)
// -----------------------------
function myNotificationReceiverKeys(): array {
    $me = myUsername();
    if ($me === '') return [];

    // Admin sees legacy "Admin" notifications + own username
    if (isAdmin()) return ['Admin', $me];

    // Everyone else uses their username
    return [$me];
}

// -----------------------------
// Internal chat channels this role can see
// Must match what you write into feedback.channel in sendreply.php
// -----------------------------
function allowedInternalChannelsForMe(): array {
    $role = myRoleId();

    // Admin: can see all internal + admin-admin
    if ($role === 1) {
        return ['admin_admin','admin_manager','admin_staff'];
    }

    // Manager: manager-manager + admin-manager
    if ($role === 2) {
        return ['manager_manager','admin_manager'];
    }

    // Gospel: gospel-gospel + admin-gospel (if you want)
    // If you DON'T want Gospel internal chat, return [] instead.
    if ($role === 3) {
        return ['gospel_gospel','admin_gospel'];
    }

    // Staff: staff-staff + admin-staff
    if ($role === 4) {
        return ['staff_staff','admin_staff'];
    }

    return [];
}
