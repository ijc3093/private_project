<?php
// /Business_only3/ajax/user_notifications_poll.php
require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../includes/identity_user.php';
require_once __DIR__ . '/../admin/controller.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

error_reporting(E_ALL);
ini_set('display_errors', '1');
try {
    $controller = new Controller();
    $dbh = $controller->pdo();

    $email = myUserEmail();
    if ($email === '') {
        echo json_encode(['ok'=>false,'unread'=>0]);
        exit;
    }

    $st = $dbh->prepare("SELECT COUNT(*) FROM notification WHERE notireceiver = :e AND is_read = 0");
    $st->execute([':e'=>$email]);

    echo json_encode(['ok'=>true,'unread'=>(int)$st->fetchColumn()]);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'unread'=>0]);
}
