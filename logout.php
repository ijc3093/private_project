<?php
// /Business_only/logout.php

require_once __DIR__ . '/includes/session_user.php';

// ✅ clears only the USER session (BUSINESS_ONLY_USER)
clearUserSession();

header("Location: index.php");
exit;
