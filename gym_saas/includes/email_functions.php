<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php'; // PHPMailer instalado vía Composer

function sendEmail($to, $subject, $body, $from = 'no-reply@sistema.com', $fromName = 'Gimnasio System') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.mailtrap.io'; // Cambiar por tu servidor SMTP real
        $mail->SMTPAuth = true;
        $mail->Username = 'TU_USUARIO'; // SMTP username
        $mail->Password = 'TU_PASS';     // SMTP password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar email: {$mail->ErrorInfo}");
        return false;
    }
}
?>
