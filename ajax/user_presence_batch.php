<?php
// /Business_only3/ajax/user_presence_batch.php
// Purpose: Update current user's last_seen and return online/offline info for MANY peers.

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/../admin/controller.php';

function online_info_local_batch(?string $lastSeen, int $thresholdSeconds = 120): array {
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

    // Always bump MY last_seen (keeps me online while I browse)
    $meId = (int)($_SESSION['user_id'] ?? 0);
    if ($meId > 0) {
        $st = $dbh->prepare("UPDATE users SET last_seen = NOW() WHERE id = :id LIMIT 1");
        $st->execute([':id' => $meId]);
    }

    // Accept peers as JSON array OR comma-separated string
    $rawPeers = $_POST['peers'] ?? '';
    $peers = [];
    if (is_array($rawPeers)) {
        $peers = $rawPeers;
    } else {
        $rawPeers = trim((string)$rawPeers);
        if ($rawPeers !== '') {
            // Try JSON first
            $maybeJson = json_decode($rawPeers, true);
            if (is_array($maybeJson)) {
                $peers = $maybeJson;
            } else {
                $peers = preg_split('/\s*,\s*/', $rawPeers) ?: [];
            }
        }
    }

    // Normalize + de-dup
    $norm = [];
    foreach ($peers as $p) {
        $c = strtoupper(trim((string)$p));
        if ($c !== '') $norm[$c] = true;
    }
    $peerCodes = array_keys($norm);

    // Hard cap for safety
    if (count($peerCodes) > 200) {
        $peerCodes = array_slice($peerCodes, 0, 200);
    }

    if (!$peerCodes) {
        echo json_encode(['ok' => true, 'data' => new stdClass()]);
        exit;
    }

    // Build IN (...) placeholders
    $ph = implode(',', array_fill(0, count($peerCodes), '?'));
    $sql = "SELECT UPPER(friend_code) AS code, last_seen, status FROM users WHERE UPPER(friend_code) IN ($ph)";
    $st = $dbh->prepare($sql);
    $st->execute($peerCodes);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $out = [];
    // Default all to Offline
    foreach ($peerCodes as $c) {
        $out[$c] = ['online' => false, 'label' => 'Offline'];
    }

    foreach ($rows as $r) {
        $code = strtoupper((string)($r['code'] ?? ''));
        if ($code === '') continue;
        if ((int)($r['status'] ?? 1) !== 1) {
            $out[$code] = ['online' => false, 'label' => 'Offline'];
            continue;
        }
        $out[$code] = online_info_local_batch((string)($r['last_seen'] ?? ''), 120);
    }

    echo json_encode(['ok' => true, 'data' => $out]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false]);
}
