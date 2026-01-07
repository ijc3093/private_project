<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendNotificationEmail(string $to, string $subject, string $htmlBody): bool
{
    $cfg = new Config();
    $mail = new PHPMailer(true);

    try {
        // Debug OFF for production (we'll debug in the test file)
        $mail->SMTPDebug  = 0;
        $mail->Debugoutput = 'html';

        $mail->isSMTP();
        $mail->Host       = $cfg->SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg->SMTP_USER;
        $mail->Password   = $cfg->SMTP_PASS;

        // STARTTLS (port 587)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // ✅ Common fix on local machines if SSL CA bundle issues exist
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom($cfg->SMTP_FROM, $cfg->SMTP_FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        return $mail->send();
    } catch (Exception $e) {
        // You can log $mail->ErrorInfo if you want
        return false;
    }
}
