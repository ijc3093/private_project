<?php
// /Business_only/admin/includes/leftbar.php

require_once __DIR__ . '/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/../controller.php';

$controller = new Controller();
$dbh = $controller->pdo();

// Role id from admin session
$roleId = (int)($_SESSION['userRole'] ?? 0);

// Default role name
$roleName = 'Unknown';

if ($roleId > 0) {
    $sql = "SELECT name FROM role WHERE idrole = :id LIMIT 1";
    $stmt = $dbh->prepare($sql);
    $stmt->execute([':id' => $roleId]);
    $roleName = $stmt->fetchColumn() ?: 'Unknown';
}

$roleKey = strtolower(trim($roleName));

function roleIs(string $roleKey, string $expected): bool {
    return $roleKey === strtolower($expected);
}

function roleIn(string $roleKey, array $list): bool {
    $list = array_map(fn($x) => strtolower(trim($x)), $list);
    return in_array($roleKey, $list, true);
}
?>

<nav class="ts-sidebar">
    <ul class="ts-sidebar-menu">

        <li class="ts-label">
            <?php echo htmlspecialchars($roleName); ?> Menu
        </li>

        <li>
            <a href="dashboard.php">
                <i class="fa fa-dashboard"></i> Dashboard
            </a>
        </li>

        <li>
            <a href="adminroles.php">
                <i class="fa fa-id-badge"></i> List Roles & Accounts
            </a>
        </li>

        <?php if (roleIs($roleKey, 'admin')): ?>
            <li>
                <a href="roleslist.php">
                    <i class="fa fa-users"></i> List & Add New Role
                </a>
            </li>

            <li>
                <a href="userlist.php">
                    <i class="fa fa-user"></i> User List
                </a>
            </li>
        <?php endif; ?>

        <?php if (roleIn($roleKey, ['admin', 'manager', 'staff'])): ?>

            <!-- ✅ NEW: COMPOSE (start a new chat by selecting username/email) -->
            <li>
                <a href="compose.php">
                    <i class="fa fa-pencil"></i> Compose
                </a>
            </li>

            <li>
                <a href="feedback.php">
                    <i class="fa fa-comments"></i> Feedback
                </a>
            </li>

            <li>
                <a href="notification.php">
                    <i class="fa fa-bell"></i> Notifications
                </a>
            </li>
        <?php endif; ?>

        <li>
            <a href="profile.php">
                <i class="fa fa-user-circle"></i> My Profile
            </a>
        </li>

        <li>
            <a href="logout.php">
                <i class="fa fa-sign-out"></i> Logout
            </a>
        </li>

    </ul>
</nav>
