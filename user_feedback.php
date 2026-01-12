<?php
// /Business_only3/user_feedback.php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/includes/user_identity.php';
require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_dt($dt): string { return $dt ? date('M d, Y h:i A', strtotime((string)$dt)) : ''; }

$meEmail = trim((string)userEmail());
$meId    = (int)userId();

if ($meEmail === '' || $meId <= 0) {
    die("Invalid session.");
}

/**
 * ✅ HARD SESSION SAFETY:
 * Make sure the session email matches the DB user row for this userId.
 * If not, you're actually still logged in as an older user (stale session).
 */
$stMe = $dbh->prepare("SELECT id, email, created_at, status FROM users WHERE id = :id LIMIT 1");
$stMe->execute([':id' => $meId]);
$meRow = $stMe->fetch(PDO::FETCH_ASSOC);

if (!$meRow) {
    // userId in session does not exist
    if (function_exists('clearUserSession')) clearUserSession();
    header("Location: index.php");
    exit;
}
if ((int)$meRow['status'] !== 1) {
    if (function_exists('clearUserSession')) clearUserSession();
    header("Location: index.php?inactive=1");
    exit;
}
if (strcasecmp((string)$meRow['email'], $meEmail) !== 0) {
    // session email doesn't match this userId -> stale/mixed session
    if (function_exists('clearUserSession')) clearUserSession();
    header("Location: index.php?session=reset");
    exit;
}

$userCreatedAt = (string)($meRow['created_at'] ?? '');
if ($userCreatedAt === '') {
    // fallback: if created_at missing, do not block by date
    $userCreatedAt = '1970-01-01 00:00:00';
}

$msg = '';
$error = '';

// filter: all | unread | read
$filter = strtolower(trim((string)($_GET['filter'] ?? 'all')));
$filter = in_array($filter, ['all','unread','read'], true) ? $filter : 'all';

$threads = [];

try {
    /**
     * =========================================
     * 1) Admin thread (Support Center)
     * =========================================
     * ✅ IMPORTANT FIX:
     * Only show messages created AFTER this user account was created.
     */
    $stA = $dbh->prepare("
        SELECT
          'Admin' AS peer_key,
          'Support Center' AS peer_display,
          MAX(f.created_at) AS last_time,
          SUM(
            CASE WHEN f.receiver = :meEmail
                   AND f.sender = 'Admin'
                   AND f.is_read = 0
                 THEN 1 ELSE 0 END
          ) AS unread_count,
          SUBSTRING_INDEX(
            GROUP_CONCAT(f.feedbackdata ORDER BY f.created_at DESC SEPARATOR ' ||| '),
            ' ||| ', 1
          ) AS last_message
        FROM feedback f
        WHERE f.channel = 'user_admin'
          AND f.created_at >= :cutoff
          AND (
               (f.sender = :meEmail2 AND f.receiver = 'Admin')
            OR (f.sender = 'Admin' AND f.receiver = :meEmail3)
          )
    ");
    $stA->execute([
        ':meEmail'  => $meEmail,
        ':meEmail2' => $meEmail,
        ':meEmail3' => $meEmail,
        ':cutoff'   => $userCreatedAt
    ]);
    $adminThread = $stA->fetch(PDO::FETCH_ASSOC);

    if ($adminThread && !empty($adminThread['last_time'])) {
        $threads[] = $adminThread;
    }

    /**
     * =========================================
     * 2) User ↔ User threads
     * =========================================
     * ✅ IMPORTANT FIX:
     * Only show user_user messages created AFTER this user account was created.
     */
    $stU = $dbh->prepare("
        SELECT
          COALESCE(NULLIF(u.friend_code,''), t.peer_email) AS peer_key,
          CASE
            WHEN COALESCE(NULLIF(uc.display_name,''),'') <> ''
              AND COALESCE(NULLIF(u.friend_code,''),'') <> ''
            THEN CONCAT(uc.display_name, ' • ', u.friend_code)
            WHEN COALESCE(NULLIF(u.friend_code,''),'') <> ''
            THEN u.friend_code
            ELSE t.peer_email
          END AS peer_display,

          MAX(t.created_at) AS last_time,

          SUM(
            CASE WHEN t.receiver = :meEmail
                   AND t.is_read = 0
                   AND t.sender = t.peer_email
                 THEN 1 ELSE 0 END
          ) AS unread_count,

          SUBSTRING_INDEX(
            GROUP_CONCAT(t.feedbackdata ORDER BY t.created_at DESC SEPARATOR ' ||| '),
            ' ||| ', 1
          ) AS last_message

        FROM (
          SELECT
            f.*,
            CASE WHEN f.sender = :meEmail2 THEN f.receiver ELSE f.sender END AS peer_email
          FROM feedback f
          WHERE f.channel = 'user_user'
            AND f.created_at >= :cutoff
            AND (f.sender = :meEmail3 OR f.receiver = :meEmail4)
        ) t

        LEFT JOIN users u
          ON u.email = t.peer_email

        LEFT JOIN user_contacts uc
          ON uc.owner_user_id = :meId
         AND uc.friend_user_id = u.id

        GROUP BY peer_key, peer_display
        HAVING last_time IS NOT NULL
        ORDER BY last_time DESC
    ");

    $stU->execute([
        ':meEmail'  => $meEmail,
        ':meEmail2' => $meEmail,
        ':meEmail3' => $meEmail,
        ':meEmail4' => $meEmail,
        ':meId'     => $meId,
        ':cutoff'   => $userCreatedAt
    ]);

    $userThreads = $stU->fetchAll(PDO::FETCH_ASSOC);
    foreach ($userThreads as $t) {
        if (!empty($t['last_time'])) {
            $threads[] = $t;
        }
    }

    // Sort threads by last_time desc
    usort($threads, function($a, $b){
        return strtotime((string)($b['last_time'] ?? '')) <=> strtotime((string)($a['last_time'] ?? ''));
    });

    // Apply read/unread filter
    if ($filter !== 'all') {
        $threads = array_values(array_filter($threads, function($t) use ($filter){
            $u = (int)($t['unread_count'] ?? 0);
            return ($filter === 'unread') ? ($u > 0) : ($u === 0);
        }));
    }

} catch (Throwable $e) {
    $error = "DB error: " . $e->getMessage();
}
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Messages</title>

  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .pill{display:inline-block;padding:4px 10px;border-radius:14px;background:#eef5ff;color:#0b5ed7;font-weight:600;font-size:12px;}
    .unread-dot{display:inline-block;min-width:18px;text-align:center;background:red;color:#fff;border-radius:10px;padding:2px 6px;font-size:11px;font-weight:700;}
    .msg-preview{max-width:520px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .actions-bar{display:flex;gap:10px;align-items:center;justify-content:space-between;margin:10px 0 15px;flex-wrap:wrap;}
    .ts-sidebar{ position: sticky; top: 70px; height: calc(100vh - 70px); overflow: auto; }
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">Messages</h2>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?php echo h($msg); ?></div><?php endif; ?>

  <div class="panel panel-default">
    <div class="panel-heading">Inbox</div>
    <div class="panel-body">

      <div class="actions-bar">
        <div>
          <a class="btn btn-default btn-sm <?php echo ($filter==='all')?'active':''; ?>" href="user_feedback.php?filter=all">All</a>
          <a class="btn btn-default btn-sm <?php echo ($filter==='unread')?'active':''; ?>" href="user_feedback.php?filter=unread">Unread</a>
          <a class="btn btn-default btn-sm <?php echo ($filter==='read')?'active':''; ?>" href="user_feedback.php?filter=read">Read</a>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a class="btn btn-default btn-sm" href="contacts.php">
            <i class="fa fa-address-book"></i> Contacts
          </a>
          <a class="btn btn-success btn-sm" href="compose.php">
            <i class="fa fa-plus"></i> New Message
          </a>
        </div>
      </div>

      <table id="chatTable" class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>Peer</th>
            <th>Last Message</th>
            <th>Last Time</th>
            <th>Unread</th>
            <th style="width:160px;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php $i=1; foreach ($threads as $t): ?>
          <?php
            $peerKey     = (string)($t['peer_key'] ?? '');
            $peerDisplay = (string)($t['peer_display'] ?? $peerKey);
          ?>
          <tr>
            <td><?php echo (int)$i++; ?></td>
            <td>
              <?php echo h($peerDisplay); ?>
              <?php if ((int)($t['unread_count'] ?? 0) > 0): ?>
                &nbsp;<span class="pill">new</span>
              <?php endif; ?>
            </td>
            <td class="msg-preview"><?php echo h((string)($t['last_message'] ?? '')); ?></td>
            <td><?php echo h(fmt_dt($t['last_time'] ?? '')); ?></td>
            <td>
              <?php if ((int)($t['unread_count'] ?? 0) > 0): ?>
                <span class="unread-dot"><?php echo (int)$t['unread_count']; ?></span>
              <?php else: ?>
                <span class="label label-success">0</span>
              <?php endif; ?>
            </td>
            <td>
              <a class="btn btn-primary btn-xs"
                 href="user_sendreply.php?reply=<?php echo urlencode($peerKey); ?>">
                Reply
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <?php if (empty($threads)): ?>
        <div class="alert alert-info" style="margin-top:12px;">No messages yet.</div>
      <?php endif; ?>

    </div>
  </div>
</div>
</div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap.min.js"></script>

<script>
$(function(){
  $('#chatTable').DataTable({
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
    order: [[3, 'desc']]
  });
});
</script>

</body>
</html>
