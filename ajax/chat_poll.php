<?php
require_once __DIR__ . '/../includes/session_user.php';
requireUserLogin();

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once __DIR__ . '/../admin/controller.php';

try {
    $controller = new Controller();
    $dbh = $controller->pdo();

    $me = $_SESSION['user_login'];
    $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

    // ✅ Fetch new messages (Admin -> this User) after last_id
    $st = $dbh->prepare("
        SELECT id, sender, receiver, feedbackdata, attachment,
               DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') AS created_at
        FROM feedback
        WHERE sender = 'Admin'
          AND receiver = :me
          AND id > :last_id
        ORDER BY id ASC
        LIMIT 100
    ");
    $st->execute([
        ':me' => $me,
        ':last_id' => $lastId
    ]);

    $msgs = $st->fetchAll(PDO::FETCH_ASSOC);

    // ✅ Auto-mark these new messages as read
    if (!empty($msgs)) {
        $ids = array_map(fn($r) => (int)$r['id'], $msgs);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $mk = $dbh->prepare("
            UPDATE feedback
            SET is_read = 1, read_at = NOW()
            WHERE id IN ($placeholders)
              AND receiver = ?
              AND sender = 'Admin'
        ");

        // bind ids + receiver at end
        $params = array_merge($ids, [$me]);
        $mk->execute($params);
    }

    echo json_encode([
        'ok' => true,
        'messages' => $msgs
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
