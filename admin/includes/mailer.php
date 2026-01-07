<?php
// /Business_only3/includes/mailer.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendNotificationEmail(string $to, string $subject, string $htmlBody): bool
{
    $cfg = new Config();
    $mail = new PHPMailer(true);

    try {
        // ✅ SMTP
        $mail->isSMTP();
        $mail->Host       = $cfg->SMTP_HOST;     // smtp.gmail.com
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg->SMTP_USER;     // gmail address
        $mail->Password   = $cfg->SMTP_PASS;     // app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $cfg->SMTP_PORT;     // 465

        // ✅ Good defaults
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        $mail->setFrom($cfg->SMTP_FROM, $cfg->SMTP_FROM_NAME);
        $mail->addAddress($to);

        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        return $mail->send();
    } catch (Exception $e) {
        // ✅ log actual SMTP error to php error log
        error_log("Mailer error: " . $mail->ErrorInfo);
        return false;
    }
}
