<?php
// /Business_only3/ajax/user_chat_unread_poll.php
require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../admin/controller.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

error_reporting(E_ALL);
ini_set('display_errors', '1');

try {
    $controller = new Controller();
    $dbh = $controller->pdo();

    $meEmail = trim($_SESSION['user_login'] ?? '');
    if ($meEmail === '') {
        echo json_encode(['ok' => false, 'unread' => 0, 'error' => 'Missing user_login session']);
        exit;
    }

    // Count unread messages addressed to this user
    $st = $dbh->prepare("
        SELECT COUNT(*)
        FROM feedback
        WHERE receiver = :me
          AND channel IN ('user_admin','user_user')
          AND is_read = 0
    ");
    $st->execute([':me' => $meEmail]);

    echo json_encode(['ok' => true, 'unread' => (int)$st->fetchColumn()]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'unread' => 0, 'error' => $e->getMessage()]);
}
