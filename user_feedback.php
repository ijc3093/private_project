<?php
// /Business_only3/user_feedback.php
require_once __DIR__ . '/includes/session_user.php';
requireUserLogin();

require_once __DIR__ . '/includes/identity_user.php';
require_once __DIR__ . '/admin/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$controller = new Controller();
$dbh = $controller->pdo();

$meEmail = trim(userEmail());
$meId    = (int)userId();

if ($meEmail === '' || $meId <= 0) {
    die("Invalid session.");
}

$msg = '';
$error = '';

function fmt_dt($dt) {
    return $dt ? date('M d, Y h:i A', strtotime($dt)) : '';
}

// filter: all | unread | read
$filter = strtolower(trim($_GET['filter'] ?? 'all'));
$filter = in_array($filter, ['all','unread','read'], true) ? $filter : 'all';

$threads = [];

try {
    // --------------------------------------------
    // 1) Admin thread (Support Center)
    // --------------------------------------------
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
          AND (
               (f.sender = :meEmail2 AND f.receiver = 'Admin')
            OR (f.sender = 'Admin' AND f.receiver = :meEmail3)
          )
    ");
    $stA->execute([
        ':meEmail'  => $meEmail,
        ':meEmail2' => $meEmail,
        ':meEmail3' => $meEmail
    ]);
    $adminThread = $stA->fetch(PDO::FETCH_ASSOC);

    if ($adminThread && !empty($adminThread['last_time'])) {
        $threads[] = $adminThread;
    }

    // --------------------------------------------
    // 2) User ↔ User threads
    // Peer display = my contact name -> friend_code -> email
    //
    // We must:
    // - Determine peer_email from each feedback row
    // - Join users to get friend_code
    // - Join user_contacts (owner_user_id=me, friend_user_id=peer_user.id)
    // --------------------------------------------
    $stU = $dbh->prepare("
        SELECT
          t.peer_email AS peer_key,

          COALESCE(
            uc.display_name,
            u.friend_code,
            t.peer_email
          ) AS peer_display,

          MAX(t.created_at) AS last_time,

          SUM(
            CASE WHEN t.receiver = :meEmail
                   AND t.is_read = 0
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
            AND (f.sender = :meEmail3 OR f.receiver = :meEmail4)
        ) t

        LEFT JOIN users u
          ON u.email = t.peer_email

        LEFT JOIN user_contacts uc
          ON uc.owner_user_id = :meId
         AND uc.friend_user_id = u.id

        GROUP BY t.peer_email, peer_display
        ORDER BY last_time DESC
    ");

    $stU->execute([
        ':meEmail'  => $meEmail,
        ':meEmail2' => $meEmail,
        ':meEmail3' => $meEmail,
        ':meEmail4' => $meEmail,
        ':meId'     => $meId
    ]);

    $userThreads = $stU->fetchAll(PDO::FETCH_ASSOC);
    foreach ($userThreads as $t) {
        if (!empty($t['last_time'])) {
            $threads[] = $t;
        }
    }

    // Sort all threads by last_time desc
    usort($threads, function($a, $b){
        return strtotime((string)$b['last_time']) <=> strtotime((string)$a['last_time']);
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
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">

  <h2 class="page-title">Messages</h2>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php endif; ?>

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
            <td><?php echo $i++; ?></td>
            <td>
              <?php echo htmlentities($peerDisplay); ?>
              <?php if ((int)($t['unread_count'] ?? 0) > 0): ?>
                &nbsp;<span class="pill">new</span>
              <?php endif; ?>
            </td>
            <td class="msg-preview"><?php echo htmlentities((string)($t['last_message'] ?? '')); ?></td>
            <td><?php echo htmlentities(fmt_dt($t['last_time'] ?? '')); ?></td>
            <td>
              <?php if ((int)($t['unread_count'] ?? 0) > 0): ?>
                <span class="unread-dot"><?php echo (int)$t['unread_count']; ?></span>
              <?php else: ?>
                <span class="label label-success">0</span>
              <?php endif; ?>
            </td>
            <td>
              <a class="btn btn-primary btn-xs"
                 href="sendreply_user.php?reply=<?php echo urlencode($peerKey); ?>">
                <i class="fa fa-mail-reply"></i> Open
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
    pageLength: 10,
    order: [[3,'desc']]
  });
});
</script>

</body>
</html>
