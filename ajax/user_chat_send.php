<?php
// /Business_only3/ajax/user_chat_send.php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../includes/config.php';
session_start();

function isEmail($s): bool { return (bool)filter_var($s, FILTER_VALIDATE_EMAIL); }

if (!isset($dbh) || !($dbh instanceof PDO)) {
    echo json_encode(['ok'=>false,'error'=>'DB connection missing']);
    exit;
}

$me = trim((string)($_SESSION['login'] ?? $_SESSION['user_email'] ?? ''));
if ($me === '' || !isEmail($me)) {
    // IMPORTANT: return JSON, not HTML redirect
    echo json_encode(['ok'=>false,'error'=>'Not logged in']);
    exit;
}

$peer = trim((string)($_POST['peer'] ?? ''));
$text = trim((string)($_POST['message'] ?? ''));

if ($peer === '' || !isEmail($peer)) {
    echo json_encode(['ok'=>false,'error'=>'Invalid peer']);
    exit;
}
if (strcasecmp($peer, $me) === 0) {
    echo json_encode(['ok'=>false,'error'=>'Cannot message yourself']);
    exit;
}
if ($text === '') {
    echo json_encode(['ok'=>false,'error'=>'Message cannot be empty']);
    exit;
}

try {
    $channel = 'user_user';

    $ins = $dbh->prepare("
        INSERT INTO feedback (sender, receiver, channel, title, feedbackdata, is_read)
        VALUES (:s, :r, :ch, 'Chat', :d, 0)
    ");
    $ins->execute([
        ':s'  => $me,
        ':r'  => $peer,
        ':ch' => $channel,
        ':d'  => $text
    ]);

    $id = (int)$dbh->lastInsertId();

    echo json_encode([
        'ok' => true,
        'message' => [
            'id' => $id,
            'sender' => $me,
            'receiver' => $peer,
            'channel' => $channel,
            'feedbackdata' => $text,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'Database error: '.$e->getMessage()]);
    exit;
}
