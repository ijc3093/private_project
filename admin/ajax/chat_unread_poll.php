<?php
// /Business_only3/admin/ajax/chat_unread_poll.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/../includes/identity.php';
require_once __DIR__ . '/../controller.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$controller = new Controller();
$dbh = $controller->pdo();

$meUser = myUsername();
$role   = myRoleId();

if ($meUser === '' || $role <= 0) {
    echo json_encode(['ok'=>false, 'error'=>'Invalid session']);
    exit;
}

try {
    $unread = 0;

    // ✅ internal unread (receiver=username)
    $internalChannels = allowedInternalChannelsForMe();
    if (!empty($internalChannels)) {
        $ph = implode(',', array_fill(0, count($internalChannels), '?'));
        $st = $dbh->prepare("
            SELECT COUNT(*)
            FROM feedback
            WHERE receiver = ?
              AND channel IN ($ph)
              AND is_read = 0
        ");
        $st->execute(array_merge([$meUser], $internalChannels));
        $unread += (int)$st->fetchColumn();
    }

    // ✅ public unread only for Admin role 1
    if (isAdmin()) {
        $st2 = $dbh->prepare("
            SELECT COUNT(*)
            FROM feedback
            WHERE channel = 'user_admin'
              AND receiver = 'Admin'
              AND is_read = 0
        ");
        $st2->execute();
        $unread += (int)$st2->fetchColumn();
    }

    echo json_encode(['ok'=>true, 'unread'=>$unread]);
    exit;

} catch (Throwable $e) {
    echo json_encode(['ok'=>false, 'error'=>'Server error']);
    exit;
}
