<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/vendor/autoload.php';

function createMailer()
{
    $mail = new PHPMailer(true);

    $smtpUsername = getenv('BREVO_SMTP_USERNAME');
    $smtpPassword = getenv('BREVO_SMTP_PASSWORD');

    if (!$smtpUsername || !$smtpPassword) {
        throw new RuntimeException('SMTP configuration is missing.');
    }

    $mail->isSMTP();
    $mail->Host       = 'smtp-relay.brevo.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUsername;
    $mail->Password   = $smtpPassword;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom(
        'ayushsathwara1219@gmail.com',
        'AICA'
    );

    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';

    return $mail;
}