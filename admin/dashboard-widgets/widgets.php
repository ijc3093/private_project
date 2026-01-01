<?php
// Role IDs
$role = $_SESSION['userRole'] ?? 0;

// Helper
function hasRole(array $allowed, int $role): bool {
    return in_array($role, $allowed, true);
}
?>

<div class="row">

<?php
// ========================
// ADMIN ONLY
// ========================
if (hasRole([1], $role)) {
?>
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-body bk-primary text-light text-center">
                <div class="stat-panel-number h1">
                    <?php echo $totalUsers ?? 0; ?>
                </div>
                <div class="stat-panel-title">Total Users</div>
            </div>
            <a href="userlist.php" class="panel-footer">View</a>
        </div>
    </div>
<?php
}
?>

<?php
// ========================
// ADMIN + MANAGER
// ========================
if (hasRole([1,2], $role)) {
?>
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-body bk-success text-light text-center">
                <div class="stat-panel-number h1">
                    <?php echo $feedbackCount ?? 0; ?>
                </div>
                <div class="stat-panel-title">Feedback</div>
            </div>
            <a href="feedback.php" class="panel-footer">View</a>
        </div>
    </div>
<?php
}
?>

<?php
// ========================
// ALL ROLES (Admin, Manager, Gospel, Staff, Teacher)
 // ========================
?>
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-body bk-info text-light text-center">
                <div class="stat-panel-number h1">
                    <?php echo $notificationCount ?? 0; ?>
                </div>
                <div class="stat-panel-title">Notifications</div>
            </div>
            <a href="notification.php" class="panel-footer">View</a>
        </div>
    </div>

</div>
