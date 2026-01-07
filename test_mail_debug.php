<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;

$cfg = new Config();

$mail = new PHPMailer(true);

$mail->SMTPDebug  = 3;                 // more detail than 2
$mail->Debugoutput = 'html';

$mail->isSMTP();
$mail->Host       = $cfg->SMTP_HOST;
$mail->SMTPAuth   = true;
$mail->Username   = $cfg->SMTP_USER;
$mail->Password   = $cfg->SMTP_PASS;

$mail->Port       = 587;
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->SMTPAutoTLS = true;

// ✅ Force IPv4 sometimes fixes weird auth/timeouts on Macs
$mail->Host = gethostbyname($cfg->SMTP_HOST);

// ✅ Local SSL relax (only for local dev)
$mail->SMTPOptions = [
  'ssl' => [
    'verify_peer'       => false,
    'verify_peer_name'  => false,
    'allow_self_signed' => true,
  ],
];

$mail->setFrom($cfg->SMTP_FROM, $cfg->SMTP_FROM_NAME);
$mail->addAddress($cfg->ADMIN_ALERT_EMAIL);

$mail->isHTML(true);
$mail->Subject = "SMTP Test (MAMP)";
$mail->Body    = "<h3>SMTP Test OK</h3><p>If you see this, Gmail SMTP works.</p>";

try {
  $mail->send();
  echo "<h2>✅ SENT OK</h2>";
} catch (Throwable $e) {
  echo "<h2>❌ FAILED</h2>";
  echo "<pre>" . htmlspecialchars($mail->ErrorInfo) . "</pre>";
}
