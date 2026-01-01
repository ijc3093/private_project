<?php
require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../admin/controller.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $controller = new Controller();
    $dbh = $controller->pdo();

    $email = $_SESSION['user_login'] ?? '';

    $stmt = $dbh->prepare("
        SELECT COUNT(*)
        FROM notification
        WHERE notireceiver = :e AND is_read = 0
    ");
    $stmt->execute([':e' => $email]);

    echo json_encode([
        'ok' => true,
        'unread' => (int)$stmt->fetchColumn()
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'unread' => 0]);
}
