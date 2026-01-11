<?php
// /Business_only3/admin/ajax/threads_poll.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/../includes/identity.php';
require_once __DIR__ . '/../controller.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$controller = new Controller();
$dbh = $controller->pdo();

$adminMode = isAdmin();
$meUser = myUsername();
$meId   = myAdminId();
$meRole = myRoleId();

if ($meUser === '' || $meId <= 0 || $meRole <= 0) {
  echo json_encode(['ok'=>false,'error'=>'Invalid session']);
  exit;
}

$view = strtolower(trim($_GET['view'] ?? ($adminMode ? 'public' : 'internal')));
$view = in_array($view, ['public','internal'], true) ? $view : 'internal';
if (!$adminMode) $view = 'internal';

$filter = strtolower(trim($_GET['filter'] ?? 'all'));
$filter = in_array($filter, ['all','unread','read'], true) ? $filter : 'all';

$internalChannels = allowedInternalChannelsForMe();

function fmt_dt($dt): string { return $dt ? date('M d, Y h:i A', strtotime($dt)) : ''; }

try {
  $threads = [];

  if ($view === 'public') {
    if (!$adminMode) {
      $threads = [];
    } else {
      // group by peer email (both directions)
      $sql = "
        SELECT
          peer,
          MAX(id) AS last_id,
          MAX(created_at) AS last_time,
          SUM(CASE
                WHEN receiver='Admin' AND sender=peer AND is_read=0 THEN 1
                ELSE 0
              END) AS unread_count
        FROM (
          SELECT
            id, sender, receiver, created_at, is_read,
            CASE WHEN sender='Admin' THEN receiver ELSE sender END AS peer
          FROM feedback
          WHERE channel='user_admin'
            AND (sender='Admin' OR receiver='Admin')
        ) x
        GROUP BY peer
        ORDER BY last_id DESC
      ";
      $st = $dbh->prepare($sql);
      $st->execute();
      $rows = $st->fetchAll(PDO::FETCH_ASSOC);

      // last message preview by last_id
      $lastMap = [];
      if ($rows) {
        $ids = array_values(array_filter(array_map(fn($r) => (int)$r['last_id'], $rows)));
        if ($ids) {
          $in = implode(',', array_fill(0, count($ids), '?'));
          $q = $dbh->prepare("SELECT id, feedbackdata FROM feedback WHERE id IN ($in)");
          $q->execute($ids);
          foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $lastMap[(int)$r['id']] = (string)($r['feedbackdata'] ?? '');
          }
        }
      }

      foreach ($rows as $r) {
        $peer = (string)($r['peer'] ?? '');
        $lastId = (int)($r['last_id'] ?? 0);
        $threads[] = [
          'peer_key' => $peer,
          'peer_display' => $peer,
          'last_time' => (string)($r['last_time'] ?? ''),
          'last_time_human' => fmt_dt((string)($r['last_time'] ?? '')),
          'unread_count' => (int)($r['unread_count'] ?? 0),
          'last_message' => (string)($lastMap[$lastId] ?? ''),
        ];
      }
    }

  } else {
    if (empty($internalChannels)) {
      $threads = [];
    } else {
      $ph = implode(',', array_fill(0, count($internalChannels), '?'));

      $sql = "
        SELECT
          COALESCE(NULLIF(a.friend_code,''), a.username) AS peer_key,
          CONCAT(
            COALESCE(NULLIF(a.fullname,''), a.username),
            ' • ',
            COALESCE(NULLIF(a.friend_code,''), a.username)
          ) AS peer_display,
          MAX(f.created_at) AS last_time,
          SUM(CASE WHEN f.is_read=0 AND f.receiver=? THEN 1 ELSE 0 END) AS unread_count,
          SUBSTRING_INDEX(
            GROUP_CONCAT(f.feedbackdata ORDER BY f.created_at DESC SEPARATOR ' ||| '),
            ' ||| ', 1
          ) AS last_message
        FROM feedback f
        JOIN admin a
          ON a.username = CASE WHEN f.sender=? THEN f.receiver ELSE f.sender END
        WHERE (f.sender=? OR f.receiver=?)
          AND f.channel IN ($ph)
        GROUP BY peer_key, peer_display
        ORDER BY last_time DESC
      ";

      $st = $dbh->prepare($sql);
      $params = array_merge([$meUser, $meUser, $meUser, $meUser], $internalChannels);
      $st->execute($params);
      $threads = $st->fetchAll(PDO::FETCH_ASSOC);

      // add human time
      foreach ($threads as &$t) {
        $t['last_time_human'] = fmt_dt((string)($t['last_time'] ?? ''));
        $t['unread_count'] = (int)($t['unread_count'] ?? 0);
        $t['last_message'] = (string)($t['last_message'] ?? '');
        $t['peer_key'] = (string)($t['peer_key'] ?? '');
        $t['peer_display'] = (string)($t['peer_display'] ?? $t['peer_key']);
      }
      unset($t);
    }
  }

  // filter unread/read/all
  if ($filter !== 'all') {
    $threads = array_values(array_filter($threads, function($t) use ($filter){
      $u = (int)($t['unread_count'] ?? 0);
      return ($filter === 'unread') ? ($u > 0) : ($u === 0);
    }));
  }

  echo json_encode(['ok'=>true,'threads'=>$threads]);
  exit;

} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'Server error']);
  exit;
}
