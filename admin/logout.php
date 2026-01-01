<?php
require_once __DIR__ . '/includes/session_admin.php';

clearAdminSession();

// ✅ extra: stop browser caching the redirect page
header("Location: index.php");
exit;
