<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

require_once 'config/config.php'; // Database connection

function sendEmail($connection, $email, $subject, $body) {
    global $smtp_username, $smtp_password, $smtp_from_email, $smtp_from_name;
    
    // Fetch the email address of the target squad
    $stmt = $connection->prepare("SELECT email_address FROM tbl_pending_verif WHERE email_address = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows === 0) {
        throw new Exception("Email address not found: $email");
    }

    $row = $result->fetch_assoc();
    $emailAddress = $row['email_address'];
    $stmt->close();

    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom($smtp_from_email, $smtp_from_name);
        $mail->addAddress($emailAddress);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        throw new Exception("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}