<?php
// /Business_only3/admin/includes/role_helpers.php
declare(strict_types=1);

require_once __DIR__ . '/../controller.php';

/**
 * Single DB handle for admin area (prevents multiple connections)
 */
function adminDbh(): PDO {
    static $dbh = null;
    if ($dbh === null) {
        $controller = new Controller();
        $dbh = $controller->pdo();
    }
    return $dbh;
}

/**
 * Read role name from your existing `role` table
 * role: (idrole, name)
 */
function roleNameById(PDO $dbh, int $roleId): string {
    if ($roleId <= 0) return '';
    $st = $dbh->prepare("SELECT name FROM role WHERE idrole = :id LIMIT 1");
    $st->execute([':id' => $roleId]);
    return strtolower(trim((string)($st->fetchColumn() ?: '')));
}

/**
 * Base role logic:
 * - Admin => admin
 * - Manager => manager
 * - Coach => manager  (your requirement)
 * - Staff => staff
 * - Gospel => gospel
 */
function baseRoleName(PDO $dbh, int $roleId): string {
    $name = roleNameById($dbh, $roleId);

    // ✅ coach behaves like manager
    if ($name === 'coach') return 'manager';

    return $name;
}

function isBaseAdmin(PDO $dbh, int $roleId): bool {
    return baseRoleName($dbh, $roleId) === 'admin';
}

function isBaseManager(PDO $dbh, int $roleId): bool {
    return baseRoleName($dbh, $roleId) === 'manager';
}

function isBaseStaff(PDO $dbh, int $roleId): bool {
    return baseRoleName($dbh, $roleId) === 'staff';
}

function isBaseGospel(PDO $dbh, int $roleId): bool {
    return baseRoleName($dbh, $roleId) === 'gospel';
}

/**
 * If role id doesn't exist in table, treat as inactive/invalid.
 */
function isActiveRole(PDO $dbh, int $roleId): bool {
    return roleNameById($dbh, $roleId) !== '';
}

/**
 * Dashboard route (you can split later if needed)
 */
function dashboardForRole(PDO $dbh, int $roleId): string {
    // Admin and Manager/Coach can share dashboard.php
    // (you can add manager-dashboard.php later if you want)
    return 'dashboard.php';
}
