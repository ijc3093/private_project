<?php
// /Business_only3/ajax/user_mark_all_read.php
require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../admin/controller.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

try {
    $email = $_SESSION['user_login'] ?? '';
    if ($email === '') {
        echo json_encode(['ok'=>false,'error'=>'Missing session']);
        exit;
    }

    $blockedTypes = [
        'New chat message',
        'Internal Chat',
        'New internal message'
    ];
    $typePH = implode(',', array_fill(0, count($blockedTypes), '?'));

    $controller = new Controller();
    $dbh = $controller->pdo();

    $st = $dbh->prepare("
        UPDATE notification
        SET is_read = 1
        WHERE notireceiver = ?
          AND is_read = 0
          AND notitype NOT IN ($typePH)
    ");
    $st->execute(array_merge([$email], $blockedTypes));

    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'Server error']);
}
