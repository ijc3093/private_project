<?php
require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

header('Content-Type: application/json');

require_once __DIR__ . '/../admin/controller.php';

try {
    $controller = new Controller();
    $dbh = $controller->pdo();

    $email = $_SESSION['user_login'];
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid id']);
        exit;
    }

    $stmt = $dbh->prepare("
        UPDATE notification
        SET is_read = 1, read_at = NOW()
        WHERE id = :id AND notireceiver = :email
    ");
    $stmt->execute([
        ':id' => $id,
        ':email' => $email
    ]);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
