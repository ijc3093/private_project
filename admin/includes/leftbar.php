<?php
require_once __DIR__ . '/session_admin.php';
requireAdminLogin();

$roleId = (int)($_SESSION['userRole'] ?? 0);

$roleMap = [
  1 => 'Admin',
  2 => 'Manager',
  3 => 'Gospel',
  4 => 'Staff'
];

$roleName = $roleMap[$roleId] ?? 'Unknown';
$isAdmin = ($roleId === 1);
?>

<nav class="ts-sidebar">
  <ul class="ts-sidebar-menu">
    <li class="ts-label"><?php echo htmlspecialchars($roleName); ?> Menu</li>

    <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
    <li><a href="profile.php"><i class="fa fa-user"></i> My Profile</a></li>

    <?php if ($isAdmin): ?>
      <li><a href="register.php"><i class="fa fa-user-plus"></i> Registration New Account</a></li>
      <li><a href="adminroles.php"><i class="fa fa-id-badge"></i> List Roles & Accounts</a></li>
      <li><a href="roleslist.php"><i class="fa fa-users"></i> List & Add New Role</a></li>
      <li><a href="userlist.php"><i class="fa fa-user"></i> User List</a></li>
      <li><a href="security-log.php"><i class="fa fa-shield"></i> Security Logs</a></li>
    <?php endif; ?>

    <!-- Everyone can chat -->
    <li><a href="compose.php"><i class="fa fa-pencil"></i> Start a Private Chat</a></li>
    <li><a href="feedback.php"><i class="fa fa-comments"></i> Chat Inbox</a></li>

    <li><a href="contacts.php"><i class="fa fa-users"></i> Contacts</a></li>
    <li><a href="logout.php"><i class="fa fa-sign-out"></i> Logout</a></li>
  </ul>
</nav>
