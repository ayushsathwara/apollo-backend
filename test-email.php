
<?php

require_once __DIR__ . '/mail-config.php';

try {
    $mail = createMailer();

    $mail->addAddress('ayushsathwara1219@gmail.com');
    $mail->Subject = 'Apollo Attendance - Email Test';
    $mail->Body = '<h2>Email test successful!</h2>
                   <p>Your Apollo Attendance System can send emails.</p>';
    $mail->AltBody = 'Your Apollo Attendance System can send emails.';

    $mail->send();

    echo 'SUCCESS: Test email sent!';
} catch (Exception $e) {
    error_log('Email test failed: ' . $e->getMessage());
    echo 'FAILED: Check the PHP error log.';
}
