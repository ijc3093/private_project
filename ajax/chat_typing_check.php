<?php
// /Business_only3/ajax/chat_typing_check.php
declare(strict_types=1);

error_reporting(0);

require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../admin/controller.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

function j(array $arr): void { echo json_encode($arr); exit; }

$meCode = strtoupper(trim((string)userFriendCode()));
$peer   = strtoupper(trim((string)($_GET['peer'] ?? '')));

if ($meCode === '' || $peer === '') j(['ok'=>false,'typing'=>false]);

try {
    $controller = new Controller();
    $dbh = $controller->pdo();

    $st = $dbh->prepare("
        SELECT ct.is_typing, ct.updated_at,
               COALESCE(NULLIF(u.name,''), NULLIF(u.username,''), u.friend_code) AS peer_name
        FROM chat_typing ct
        JOIN users u ON u.friend_code = ct.sender_code
        WHERE ct.sender_code = :peer
          AND ct.receiver_code = :me
        LIMIT 1
    ");
    $st->execute([':peer'=>$peer, ':me'=>$meCode]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    $typing = false;
    $peerName = '';

    if ($row) {
        $peerName = (string)($row['peer_name'] ?? '');
        $is = (int)($row['is_typing'] ?? 0);
        $ts = strtotime((string)($row['updated_at'] ?? '')) ?: 0;

        // show typing if updated within 6 seconds
        if ($is === 1 && $ts > 0 && (time() - $ts) <= 6) $typing = true;
    }

    j(['ok'=>true,'typing'=>$typing,'peer_name'=>$peerName]);
} catch (Throwable $e) {
    j(['ok'=>false,'typing'=>false]);
}
