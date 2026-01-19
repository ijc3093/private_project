<?php
// /Business_only3/ajax/user_presence_ping.php
// Purpose: Update current user's last_seen and return online/offline info for a peer.

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../admin/controller.php';

function online_info_local(?string $lastSeen, int $thresholdSeconds = 120): array {
    $lastSeen = (string)($lastSeen ?? '');
    if ($lastSeen === '') return ['online' => false, 'label' => 'Offline'];
    $ts = strtotime($lastSeen);
    if (!$ts) return ['online' => false, 'label' => 'Offline'];
    $online = (time() - $ts) <= $thresholdSeconds;
    if ($online) return ['online' => true, 'label' => 'Online'];

    $delta = time() - $ts;
    if ($delta < 3600) {
        $m = max(1, (int)floor($delta / 60));
        return ['online' => false, 'label' => 'Last seen ' . $m . ' min ago'];
    }
    if ($delta < 86400) {
        $h = max(1, (int)floor($delta / 3600));
        return ['online' => false, 'label' => 'Last seen ' . $h . ' hr ago'];
    }
    return ['online' => false, 'label' => 'Last seen ' . date('M j, Y h:i A', $ts)];
}

try {
    $controller = new Controller();
    $dbh = $controller->pdo();

    // Always bump MY last_seen
    $meId = (int)($_SESSION['user_id'] ?? 0);
    if ($meId > 0) {
        $st = $dbh->prepare("UPDATE users SET last_seen = NOW() WHERE id = :id LIMIT 1");
        $st->execute([':id' => $meId]);
    }

    $peer = strtoupper(trim((string)($_GET['peer'] ?? '')));
    if ($peer === '') {
        echo json_encode(['ok' => true, 'online' => true, 'label' => 'Online']);
        exit;
    }

    $stPeer = $dbh->prepare("SELECT last_seen, status FROM users WHERE UPPER(friend_code) = :c LIMIT 1");
    $stPeer->execute([':c' => $peer]);
    $row = $stPeer->fetch(PDO::FETCH_ASSOC);

    if (!$row || (int)($row['status'] ?? 1) !== 1) {
        echo json_encode(['ok' => true, 'online' => false, 'label' => 'Offline']);
        exit;
    }

    $info = online_info_local((string)($row['last_seen'] ?? ''), 120);
    echo json_encode(['ok' => true] + $info);
} catch (Throwable $e) {
    echo json_encode(['ok' => false]);
}
