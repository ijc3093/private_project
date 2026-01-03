<?php
require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../includes/identity_user.php';
require_once __DIR__ . '/../admin/controller.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $controller = new Controller();
    $dbh = $controller->pdo();

    $email = myUserEmail();

    $st = $dbh->prepare("
      UPDATE notification
      SET is_read = 1, read_at = NOW()
      WHERE notireceiver = :e AND is_read = 0
    ");
    $st->execute([':e'=>$email]);

    echo json_encode(['ok'=>true,'updated'=>$st->rowCount()]);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'Server error']);
}
