<?php
// /Business_only3/ajax/chat_typing_check.php
// Returns typing status + peer display name (nickname if saved)

declare(strict_types=1);
error_reporting(0);

require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../includes/user_identity.php';
require_once __DIR__ . '/../admin/controller.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function j(array $arr): void {
    echo json_encode($arr);
    exit;
}

$meCode = strtoupper(trim((string)userFriendCode()));
$meId   = (int)userId();
$peer   = strtoupper(trim((string)($_GET['peer'] ?? '')));

if ($meCode === '' || $peer === '') j(['ok'=>false]);

if (!preg_match('/^[A-Z]{3}-[A-Z0-9]{4}-[A-Z0-9]{4}$/i', $peer)) {
    j(['ok'=>false]);
}

try {
    $controller = new Controller();
    $dbh = $controller->pdo();

    // peer display: prefer my saved nickname, else friend code
    $peerDisplay = $peer;
    $st = $dbh->prepare("
        SELECT
            COALESCE(NULLIF(uc.display_name,''), u.friend_code) AS display
        FROM users u
        LEFT JOIN user_contacts uc
            ON uc.owner_user_id = :meId
           AND uc.friend_user_id = u.id
        WHERE u.friend_code = :peer
          AND u.status = 1
        LIMIT 1
    ");
    $st->execute([':meId'=>$meId, ':peer'=>$peer]);
    $peerDisplay = (string)($st->fetchColumn() ?: $peer);

    $st = $dbh->prepare("
        SELECT is_typing, updated_at
        FROM chat_typing
        WHERE sender_code = :peer
          AND receiver_code = :me
        LIMIT 1
    ");
    $st->execute([':peer'=>$peer, ':me'=>$meCode]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    $typing = false;
    if ($row) {
        $is = (int)($row['is_typing'] ?? 0);
        $ts = strtotime((string)($row['updated_at'] ?? '')) ?: 0;
        // allow 6 seconds window
        if ($is === 1 && $ts > 0 && (time() - $ts) <= 6) $typing = true;
    }

    j(['ok'=>true, 'typing'=>$typing, 'peer_name'=>$peerDisplay]);
} catch (Throwable $e) {
    j(['ok'=>false]);
}
