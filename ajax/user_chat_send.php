<?php
// /Business_only3/ajax/user_chat_send.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../admin/controller.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$meCode = function_exists('userFriendCode') ? userFriendCode() : trim((string)($_SESSION['user_friend_code'] ?? ''));
$meCode = trim($meCode);

$to = trim((string)($_POST['to'] ?? ''));
$msg = trim((string)($_POST['message'] ?? ''));

if ($meCode === '' || $to === '' || $msg === '') {
    echo json_encode(['ok'=>false,'error'=>'Missing fields']);
    exit;
}

// friend code validation
if (!preg_match('/^[A-Z]{3}-[A-Z0-9]{4}-[A-Z0-9]{4}$/i', $to)) {
    echo json_encode(['ok'=>false,'error'=>'Invalid peer']);
    exit;
}

try {
    $controller = new Controller();
    $dbh = $controller->pdo();

    // ensure peer exists
    $st = $dbh->prepare("SELECT id, status FROM users WHERE UPPER(friend_code)=UPPER(:c) LIMIT 1");
    $st->execute([':c'=>$to]);
    $peer = $st->fetch(PDO::FETCH_ASSOC);

    if (!$peer || (int)($peer['status'] ?? 1) !== 1) {
        echo json_encode(['ok'=>false,'error'=>'Peer not found']);
        exit;
    }

    // insert message (store friend_code -> friend_code)
    $ins = $dbh->prepare("
        INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, attachment, is_read, created_at)
        VALUES (:s, :r, 'user_user', '', :m, NULL, 0, NOW())
    ");
    $ins->execute([':s'=>$meCode, ':r'=>$to, ':m'=>$msg]);

    $id = (int)$dbh->lastInsertId();

    echo json_encode([
        'ok'=>true,
        'item'=>[
            'id'=>$id,
            'is_me'=>true,
            'feedbackdata'=>$msg,
            'time'=>date('M d, Y h:i A'),
        ]
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'Server error']);
    exit;
}
