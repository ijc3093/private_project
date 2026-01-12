<?php
// /Business_only3/admin/includes/leftbar.php
declare(strict_types=1);

require_once __DIR__ . '/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/role_helpers.php';

$dbh = adminDbh();
$rawRoleId = (int)($_SESSION['userRole'] ?? 0);

$base = baseRoleName($dbh, $rawRoleId);   // coach -> manager
if ($base === '') $base = 'unknown';

function roleIs(string $base, string $expected): bool {
    return strtolower($base) === strtolower($expected);
}
function roleIn(string $base, array $list): bool {
    $base = strtolower($base);
    $list = array_map(fn($x) => strtolower(trim((string)$x)), $list);
    return in_array($base, $list, true);
}
?>

<nav class="ts-sidebar">
  <ul class="ts-sidebar-menu">
    <li class="ts-label"><?php echo htmlspecialchars(ucfirst($base)); ?> Menu</li>

    <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
    <li><a href="profile.php"><i class="fa fa-user"></i> My Profile</a></li>

    <?php if (roleIs($base,'admin')): ?>
      <li><a href="register.php"><i class="fa fa-user-plus"></i> Registration New Account</a></li>
      <li><a href="adminroles.php"><i class="fa fa-id-badge"></i> List Roles & Accounts</a></li>
      <li><a href="roleslist.php"><i class="fa fa-users"></i> List & Add New Role</a></li>
      <li><a href="userlist.php"><i class="fa fa-user"></i> User List</a></li>
      <li><a href="security-log.php"><i class="fa fa-shield"></i> Security Logs</a></li>
    <?php endif; ?>

    <?php if (roleIn($base, ['admin','manager','staff'])): ?>
      <li><a href="compose.php"><i class="fa fa-pencil"></i> Start a Private Chat</a></li>
      <li><a href="feedback.php?view=internal"><i class="fa fa-comments"></i> Chat Inbox</a></li>
    <?php endif; ?>

    <li><a href="contacts.php"><i class="fa fa-users"></i> Contacts</a></li>
    <li><a href="logout.php"><i class="fa fa-sign-out"></i> Logout</a></li>
  </ul>
</nav>
