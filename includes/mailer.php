<?php
// /Business_only/includes/mailer.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendNotificationEmail(string $to, string $subject, string $htmlBody): bool
{
    $cfg = new Config();

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $cfg->SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg->SMTP_USER;
        $mail->Password   = $cfg->SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $cfg->SMTP_PORT;

        $mail->setFrom($cfg->SMTP_FROM, $cfg->SMTP_FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        return $mail->send();
    } catch (Exception $e) {
        // You can log: $mail->ErrorInfo
        return false;
    }
}
