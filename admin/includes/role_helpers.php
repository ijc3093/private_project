<?php
// /Business_only3/admin/includes/role_helpers.php
declare(strict_types=1);

require_once __DIR__ . '/../controller.php';

if (!function_exists('adminDbh')) {
  function adminDbh(): PDO {
    static $dbh = null;
    if ($dbh === null) {
      $controller = new Controller();
      $dbh = $controller->pdo();
    }
    return $dbh;
  }
}

// if (!function_exists('getRoleRow')) {
//   function getRoleRow(PDO $dbh, int $roleId): ?array {
//     if ($roleId <= 0) return null;
//     $st = $dbh->prepare("SELECT idrole, name, inherits_from, status FROM role WHERE idrole=:id LIMIT 1");
//     $st->execute([':id' => $roleId]);
//     $r = $st->fetch(PDO::FETCH_ASSOC);
//     return $r ?: null;
//   }
// }

/**
 * Get role row from YOUR table: role(idrole, name, inherits_from?, status?)
 * If your role table does NOT have inherits_from/status columns, this still works
 * and will fallback safely.
 */
function getRoleRow(PDO $dbh, int $roleId): ?array {
    if ($roleId <= 0) return null;

    // Detect columns safely (works even if inherits_from/status not present)
    // We'll just select common columns; missing ones become null.
    $st = $dbh->prepare("
        SELECT
            idrole,
            name,
            COALESCE(inherits_from, NULL) AS inherits_from,
            COALESCE(status, 1) AS status
        FROM role
        WHERE idrole = :id
        LIMIT 1
    ");
    $st->execute([':id' => $roleId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}


function resolveBaseRoleId(PDO $dbh, int $roleId): int {
    $seen = [];
    $cur = $roleId;

    for ($i = 0; $i < 30; $i++) {
        if ($cur <= 0) return 0;
        if (isset($seen[$cur])) return $roleId; // cycle protection
        $seen[$cur] = true;

        $row = getRoleRow($dbh, $cur, false);
        if (!$row) return 0;

        if ((int)$row['status'] !== 1) return 0;

        $parent = (int)($row['inherits_from'] ?? 0);
        if ($parent <= 0) return $cur; // base role
        $cur = $parent;
    }
    return $roleId;
}

/**
 * Resolve base role name via inheritance chain (Coach -> Manager).
 * If inherits_from column does not exist or is NULL: base is itself.
 */
function baseRoleName(PDO $dbh, int $roleId): string {
    $seen = [];
    $cur = $roleId;

    for ($i = 0; $i < 30; $i++) {
        if ($cur <= 0) return '';

        if (isset($seen[$cur])) break; // cycle protection
        $seen[$cur] = true;

        $row = getRoleRow($dbh, $cur);
        if (!$row) return '';

        // inactive role => treat as invalid
        if ((int)($row['status'] ?? 1) !== 1) return '';

        $parent = (int)($row['inherits_from'] ?? 0);
        if ($parent <= 0) {
            return strtolower(trim((string)($row['name'] ?? '')));
        }
        $cur = $parent;
    }

    // fallback
    $row = getRoleRow($dbh, $roleId);
    return strtolower(trim((string)($row['name'] ?? '')));
}



function roleNameRaw(PDO $dbh, int $roleId): string
{
    $row = getRoleRow($dbh, $roleId);
    return strtolower(trim((string)($row['name'] ?? '')));
}


/**
 * Is role active (and chain active)
 */
function isActiveRole(PDO $dbh, int $roleId): bool {
    return baseRoleName($dbh, $roleId) !== '';
}

/**
 * For permission checks: "admin" base role is super admin
 */
function isBaseAdmin(PDO $dbh, int $roleId): bool {
    return baseRoleName($dbh, $roleId) === 'admin';
}

if (!function_exists('baseRoleName')) {
  function baseRoleName(PDO $dbh, int $roleId): string {
    $baseId = resolveBaseRoleId($dbh, $roleId);
    if ($baseId <= 0) return '';
    $row = getRoleRow($dbh, $baseId);
    return strtolower(trim((string)($row['name'] ?? '')));
  }
}

if (!function_exists('isActiveRole')) {
  function isActiveRole(PDO $dbh, int $roleId): bool {
    return resolveBaseRoleId($dbh, $roleId) > 0;
  }
}

if (!function_exists('dashboardForRole')) {
  function dashboardForRole(PDO $dbh, int $roleId): string {
    // You can route different dashboards later if you want.
    // For now everyone goes to dashboard.php
    $base = baseRoleName($dbh, $roleId);
    if ($base === '') return 'index.php?inactiveRole=1';
    return 'dashboard.php';
  }
}

/**
 * Effective role id = base role id
 * (coach -> manager)
 */
function effectiveRoleId(PDO $dbh, int $roleId): int {
    return resolveBaseRoleId($dbh, $roleId);
}

/**
 * Current role name (not base) – this is what header should show ("Coach")
 */
function currentRoleName(PDO $dbh, int $roleId): string {
    $row = getRoleRow($dbh, $roleId, false);
    return trim((string)($row['name'] ?? ''));
}


/**
 * For display in header (show the raw role name like "Coach")
 */
function roleDisplayName(PDO $dbh, int $roleId): string {
    $row = getRoleRow($dbh, $roleId);
    return trim((string)($row['name'] ?? ''));
}


/**
 * Dashboard routing (keep it simple: one dashboard)
 */
function dashboardForRole(PDO $dbh, int $roleId): string {
    // You can route by baseRoleName() later if you want
    return 'dashboard.php';
}



// /**
//  * Route dashboard by base role (you can change pages later)
//  */
// function dashboardForRole(PDO $dbh, int $roleId): string {
//     $base = baseRoleName($dbh, $roleId);

//     // For now everyone uses the same dashboard page
//     if (in_array($base, ['admin','manager','staff','gospel'], true)) {
//         return 'dashboard.php';
//     }
//     return 'dashboard.php';
// }