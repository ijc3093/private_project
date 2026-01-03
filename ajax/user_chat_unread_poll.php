<?php
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

    $meId = myUserId();

    // Count messages in conversations where I am a participant, that I haven't marked read
    $st = $dbh->prepare("
      SELECT COUNT(*)
      FROM messages m
      JOIN conversations c ON c.id = m.conversation_id
      JOIN conversation_participants p ON p.conversation_id = c.id AND p.user_id = :me
      LEFT JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = :me2
      WHERE mr.message_id IS NULL
        AND m.sender_user_id <> :me3
    ");
    $st->execute([':me'=>$meId, ':me2'=>$meId, ':me3'=>$meId]);

    echo json_encode(['ok'=>true,'unread'=>(int)$st->fetchColumn()]);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'unread'=>0]);
}
