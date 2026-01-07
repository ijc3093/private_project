<?php
// /Business_only3/admin/avatar_admin.php
require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/controller.php';

error_reporting(E_ALL);
ini_set('display_errors', '0'); // do not break image output

$controller = new Controller();
$dbh = $controller->pdo();

// Prevent caching (very important)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

$loginValue = $_SESSION['admin_login'] ?? '';
$loginValue = trim($loginValue);

if ($loginValue === '') {
    http_response_code(401);
    exit;
}

try {
    $st = $dbh->prepare("
        SELECT image_blob, image_type
        FROM admin
        WHERE username = :u OR email = :e
        LIMIT 1
    ");
    $st->execute([
        ':u' => $loginValue,
        ':e' => $loginValue
    ]);

    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['image_blob'])) {
        // Fallback to default image file
        $fallback = __DIR__ . '/../images/profile.jpg';
        if (is_file($fallback)) {
            header("Content-Type: image/jpeg");
            readfile($fallback);
            exit;
        }
        // If even fallback missing, return 404
        http_response_code(404);
        exit;
    }

    $type = $row['image_type'] ?: 'image/jpeg';

    // Safety: only allow image content-types
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($type, $allowed, true)) {
        $type = 'image/jpeg';
    }

    header("Content-Type: " . $type);
    echo $row['image_blob'];
    exit;

} catch (Throwable $e) {
    // If anything fails, fallback image
    $fallback = __DIR__ . '/../images/profile.jpg';
    if (is_file($fallback)) {
        header("Content-Type: image/jpeg");
        readfile($fallback);
        exit;
    }
    http_response_code(500);
    exit;
}
