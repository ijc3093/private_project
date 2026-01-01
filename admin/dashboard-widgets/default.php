<?php
/**
 * admin/dashboard-widgets/default.php
 * Uses: $dbh, $roleName, $userRoleId
 * Shows widgets depending on role.
 */

// normalize role name
$roleKey = strtolower(trim($roleName));

function statCount(PDO $dbh, string $sql, array $params = []): int {
    $st = $dbh->prepare($sql);
    $st->execute($params);
    return (int)$st->fetchColumn();
}

// --- Counts (safe even if tables empty)
$totalUsers     = statCount($dbh, "SELECT COUNT(*) FROM users");
$totalAdmins    = statCount($dbh, "SELECT COUNT(*) FROM admin");
$totalRoles     = statCount($dbh, "SELECT COUNT(*) FROM role");

$adminFeedback  = statCount($dbh, "SELECT COUNT(*) FROM feedback WHERE receiver = :r", [':r' => 'Admin']);
$adminNoti      = statCount($dbh, "SELECT COUNT(*) FROM notification WHERE notireceiver = :r", [':r' => 'Admin']);
$deletedUsers   = statCount($dbh, "SELECT COUNT(*) FROM deleteduser");

// Permission rules (NO need to create new pages for Teacher)
$isAdmin   = ($roleKey === 'admin');
$isManager = ($roleKey === 'manager');
$isGospel  = ($roleKey === 'gospel');
$isStaff   = ($roleKey === 'staff');
$isTeacher = ($roleKey === 'teacher');

// Who can see what:
$canManageUsers  = $isAdmin;
$canManageRoles  = $isAdmin;
$canSeeFeedback  = ($isAdmin || $isManager || $isTeacher); // you asked Teacher should work automatically
$canSeeNoti      = ($isAdmin || $isManager || $isTeacher);
$canSeeDeletes   = $isAdmin;

?>

<div class="row">

    <?php if ($canManageUsers): ?>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="panel panel-default">
            <div class="panel-body bk-primary text-light">
                <div class="stat-panel text-center">
                    <div class="stat-panel-number h1"><?php echo htmlentities($totalUsers); ?></div>
                    <div class="stat-panel-title text-uppercase">Total Public Users</div>
                </div>
            </div>
            <a href="userlist.php" class="block-anchor panel-footer">Full Detail <i class="fa fa-arrow-right"></i></a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canManageRoles): ?>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="panel panel-default">
            <div class="panel-body bk-info text-light">
                <div class="stat-panel text-center">
                    <div class="stat-panel-number h1"><?php echo htmlentities($totalRoles); ?></div>
                    <div class="stat-panel-title text-uppercase">Roles</div>
                </div>
            </div>
            <a href="roleslist.php" class="block-anchor panel-footer">Full Detail <i class="fa fa-arrow-right"></i></a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canSeeFeedback): ?>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="panel panel-default">
            <div class="panel-body bk-success text-light">
                <div class="stat-panel text-center">
                    <div class="stat-panel-number h1"><?php echo htmlentities($adminFeedback); ?></div>
                    <div class="stat-panel-title text-uppercase">Feedback</div>
                </div>
            </div>
            <a href="feedback.php" class="block-anchor panel-footer">Full Detail <i class="fa fa-arrow-right"></i></a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canSeeNoti): ?>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="panel panel-default">
            <div class="panel-body bk-danger text-light">
                <div class="stat-panel text-center">
                    <div class="stat-panel-number h1"><?php echo htmlentities($adminNoti); ?></div>
                    <div class="stat-panel-title text-uppercase">Notifications</div>
                </div>
            </div>
            <a href="notification.php" class="block-anchor panel-footer">Full Detail <i class="fa fa-arrow-right"></i></a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canSeeDeletes): ?>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="panel panel-default">
            <div class="panel-body bk-warning text-light">
                <div class="stat-panel text-center">
                    <div class="stat-panel-number h1"><?php echo htmlentities($deletedUsers); ?></div>
                    <div class="stat-panel-title text-uppercase">Deleted Users</div>
                </div>
            </div>
            <a href="deleteduser.php" class="block-anchor panel-footer">Full Detail <i class="fa fa-arrow-right"></i></a>
        </div>
    </div>
    <?php endif; ?>

</div>

<hr>

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">Quick Info</div>
            <div class="panel-body">
                <p><b>Your role:</b> <?php echo htmlentities($roleName); ?></p>
                <p><b>Total staff accounts (admin table):</b> <?php echo htmlentities($totalAdmins); ?></p>

                <?php if ($isTeacher): ?>
                    <div class="alert alert-info" style="margin-top:10px;">
                        You are a <b>Teacher</b>. You can view feedback and notifications without creating a new dashboard page.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
