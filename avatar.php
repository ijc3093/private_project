<?php
require_once __DIR__ . '/includes/session_user.php';
require_once __DIR__ . '/admin/controller.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$controller = new Controller();
$dbh = $controller->pdo();

$userEmail = $_SESSION['user_login'] ?? '';
if ($userEmail === '') {
    // not logged in -> show default image
    $default = __DIR__ . '/images/default.jpg';
    header("Content-Type: image/jpeg");
    if (is_file($default)) readfile($default);
    exit;
}

$stmt = $dbh->prepare("SELECT image_blob, image_type FROM users WHERE email = :e LIMIT 1");
$stmt->execute([':e' => $userEmail]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['image_blob'])) {
    $default = __DIR__ . '/images/default.jpg';
    header("Content-Type: image/jpeg");
    if (is_file($default)) readfile($default);
    exit;
}

$type = !empty($row['image_type']) ? $row['image_type'] : 'image/jpeg';
header("Content-Type: " . $type);
echo $row['image_blob'];
exit;
