<?php
// /Business_only3/ajax/user_chat_unread_poll.php
require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../admin/controller.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

try {
    $controller = new Controller();
    $dbh = $controller->pdo();

    $meEmail = trim($_SESSION['user_login'] ?? '');
    if ($meEmail === '') {
        echo json_encode(['ok'=>false,'unread'=>0,'error'=>'Missing session user_login']);
        exit;
    }

    $unread = 0;

    // 1) Admin -> User unread (user_admin channel)
    $st1 = $dbh->prepare("
        SELECT COUNT(*)
        FROM feedback
        WHERE channel = 'user_admin'
          AND receiver = :me
          AND sender = 'Admin'
          AND is_read = 0
    ");
    $st1->execute([':me' => $meEmail]);
    $unread += (int)$st1->fetchColumn();

    // 2) User -> User unread (user_user channel)
    $st2 = $dbh->prepare("
        SELECT COUNT(*)
        FROM feedback
        WHERE channel = 'user_user'
          AND receiver = :me
          AND is_read = 0
    ");
    $st2->execute([':me' => $meEmail]);
    $unread += (int)$st2->fetchColumn();

    echo json_encode(['ok'=>true,'unread'=>(int)$unread]);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'unread'=>0,'error'=>$e->getMessage()]);
}
