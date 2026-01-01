<?php
require_once __DIR__ . '/../includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/../includes/identity.php';
require_once __DIR__ . '/../controller.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

try {
    $controller = new Controller();
    $dbh = $controller->pdo();

    $me   = myUsername();   // username from session
    $role = myRoleId();     // 1=Admin, 2=Manager, 3=Gospel, 4=Staff

    if ($me === '') {
        echo json_encode(['ok' => false, 'unread' => 0, 'error' => 'Missing session username']);
        exit;
    }

    $unread = 0;

    // ✅ 1) Admin shared inbox: users -> Admin (receiver='Admin', channel='user_admin')
    if ($role === 1) {
        $stmt = $dbh->prepare("
            SELECT COUNT(*)
            FROM feedback
            WHERE receiver = 'Admin'
              AND channel  = 'user_admin'
              AND is_read  = 0
        ");
        $stmt->execute();
        $unread += (int)$stmt->fetchColumn();
    }

    // ✅ 2) Internal chats to my username
    // MUST match your sendreply.php channels:
    // admin_manager, admin_staff, manager_staff
    $internalChannels = ['admin_manager', 'admin_staff', 'manager_staff'];
    $ph = implode(',', array_fill(0, count($internalChannels), '?'));

    $stmt2 = $dbh->prepare("
    SELECT COUNT(*)
    FROM feedback
    WHERE receiver = :me
      AND channel IN ('admin_manager','admin_staff')
      AND is_read = 0");
    $stmt2->execute([':me' => $me]);
    $unread += (int)$stmt2->fetchColumn();
    
    $stmt2->execute(array_merge([$me], $internalChannels));
    $unread += (int)$stmt2->fetchColumn();

    echo json_encode(['ok' => true, 'unread' => (int)$unread]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'unread' => 0, 'error' => 'Server error']);
}
