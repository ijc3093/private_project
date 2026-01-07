<?php
// /Business_only3/ajax/user_notifications_poll.php
require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../admin/controller.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

try {
    $email = trim($_SESSION['user_login'] ?? '');
    if ($email === '') {
        echo json_encode(['ok' => false, 'unread' => 0, 'error' => 'No session']);
        exit;
    }

    $controller = new Controller();
    $dbh = $controller->pdo();

    // ✅ block chat-related notifications from badge count
    $blockedTypes = [
        'New chat message',
        'Internal Chat',
        'New internal message'
    ];

    if (!empty($blockedTypes)) {
        $ph = implode(',', array_fill(0, count($blockedTypes), '?'));

        $st = $dbh->prepare("
            SELECT COUNT(*)
            FROM notification
            WHERE notireceiver = ?
              AND is_read = 0
              AND notitype NOT IN ($ph)
        ");
        $st->execute(array_merge([$email], $blockedTypes));
    } else {
        // fallback if no blocked types
        $st = $dbh->prepare("
            SELECT COUNT(*)
            FROM notification
            WHERE notireceiver = ?
              AND is_read = 0
        ");
        $st->execute([$email]);
    }

    $unread = (int)$st->fetchColumn();

    echo json_encode(['ok' => true, 'unread' => $unread]);
} catch (Throwable $e) {
    // You can temporarily debug like this:
    // echo json_encode(['ok'=>false,'unread'=>0,'error'=>$e->getMessage()]);
    echo json_encode(['ok' => false, 'unread' => 0, 'error' => 'Server error']);
}
