<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/mailer.php';

$to = "YOUR_GMAIL@gmail.com"; // test sending to yourself

$ok = sendNotificationEmail(
  $to,
  "SMTP Test from Business_only3",
  "<h3>Hello!</h3><p>This is a test email.</p>"
);

echo $ok ? "✅ SENT" : "❌ FAILED (check PHP error log).";
