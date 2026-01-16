<?php
// /Business_only3/user_feedback.php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_user.php';
require_once __DIR__ . '/includes/chat_lib.php';

requireUserLogin();

// Just go to the real UI page
header("Location: messages.php");
exit;
