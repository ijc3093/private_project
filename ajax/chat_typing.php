<?php
// /Business_only3/ajax/chat_typing.php
declare(strict_types=1);

error_reporting(0);

require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../admin/controller.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function j($arr) { echo json_encode($arr); exit; }

$meCode = strtoupper(trim((string)userFriendCode()));
if ($meCode === '') j(['ok'=>false,'error'=>'Missing my friend code']);

$peer = strtoupper(trim((string)($_POST['peer'] ?? $_GET['peer'] ?? '')));
if ($peer === '') j(['ok'=>false,'error'=>'Missing peer']);

if (!preg_match('/^[A-Z]{3}-[A-Z0-9]{4}-[A-Z0-9]{4}$/i', $peer)) {
    j(['ok'=>false,'error'=>'Friend code required']);
}

$typingRaw = (string)($_POST['typing'] ?? $_GET['typing'] ?? '1');
$typing = ($typingRaw === '1' || strtolower($typingRaw) === 'true') ? 1 : 0;

try {
    $controller = new Controller();
    $dbh = $controller->pdo();

    // validate peer exists
    $st = $dbh->prepare("SELECT friend_code FROM users WHERE UPPER(friend_code)=:c AND status=1 LIMIT 1");
    $st->execute([':c'=>$peer]);
    if (!$st->fetchColumn()) j(['ok'=>false,'error'=>'Peer not found']);

    // chat_typing schema expectation:
    // sender_code, receiver_code, is_typing, updated_at
    $sql = "
        INSERT INTO chat_typing (sender_code, receiver_code, is_typing, updated_at)
        VALUES (:s, :r, :t, NOW())
        ON DUPLICATE KEY UPDATE
            is_typing = VALUES(is_typing),
            updated_at = NOW()
    ";

    $q = $dbh->prepare($sql);
    $q->execute([':s'=>$meCode, ':r'=>$peer, ':t'=>$typing]);

    j(['ok'=>true]);
} catch (Throwable $e) {
    j(['ok'=>false,'error'=>'Server error']);
}
