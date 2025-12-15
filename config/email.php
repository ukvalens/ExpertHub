<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail($email, $name, $verification_token) {
    $verification_link = "http://localhost/ExpertHUB/verify.php?token=" . $verification_token;
    $subject = "Verify Your ExpertHub Account";
    
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #0077B6;'>Welcome to ExpertHub!</h2>
            <p>Hi $name,</p>
            <p>Thank you for registering with ExpertHub. Please click the button below to verify your email address:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$verification_link' style='background-color: #0077B6; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Verify Account</a>
            </div>
            <p>Or copy and paste this link in your browser:</p>
            <p style='word-break: break-all;'>$verification_link</p>
            <p>This link will expire in 24 hours.</p>
            <hr>
            <p style='color: #666; font-size: 12px;'>If you didn't create an account, please ignore this email.</p>
        </div>
    </body>
    </html>";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: ExpertHub <noreply@experthub.com>" . "\r\n";
    
    // Use PHP's built-in mail function (for development)
    return mail($email, $subject, $message, $headers);
}

function sendNewRequestNotification($provider_email, $provider_name, $service_title, $customer_name, $customer_email, $request_details) {
    $subject = "New Service Request - $service_title";
    
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #0077B6;'>New Service Request!</h2>
            <p>Hi $provider_name,</p>
            <p>You have received a new service request for <strong>$service_title</strong>.</p>
            
            <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                <h3 style='color: #333; margin-top: 0;'>Request Details:</h3>
                <p><strong>Customer:</strong> $customer_name</p>
                <p><strong>Email:</strong> $customer_email</p>
                <p><strong>Service:</strong> $service_title</p>
                <div style='margin-top: 15px;'>
                    <strong>Details:</strong><br>
                    " . nl2br(htmlspecialchars($request_details)) . "
                </div>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='http://localhost/ExpertHUB/provider_dashboard.php' style='background-color: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Request</a>
            </div>
            
            <p>Please contact the customer soon to schedule the service.</p>
            <hr>
            <p style='color: #666; font-size: 12px;'>This notification was sent via ExpertHub platform.</p>
        </div>
    </body>
    </html>";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: ExpertHub <noreply@experthub.com>" . "\r\n";
    
    return mail($provider_email, $subject, $message, $headers);
}

function sendNewMessageNotification($recipient_email, $recipient_name, $sender_name, $service_title, $message_content, $order_number) {
    $subject = "New Message - $service_title";
    
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #0077B6;'>New Message Received!</h2>
            <p>Hi $recipient_name,</p>
            <p>You have received a new message from <strong>$sender_name</strong> regarding order #$order_number.</p>
            
            <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                <h3 style='color: #333; margin-top: 0;'>Service: $service_title</h3>
                <div style='background-color: white; padding: 15px; border-left: 4px solid #0077B6; margin-top: 15px;'>
                    <strong>Message:</strong><br>
                    " . nl2br(htmlspecialchars($message_content)) . "
                </div>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='http://localhost/ExpertHUB/app/views/customer/messages.php?order_id=" . $order_number . "&lang=en' style='background-color: #0077B6; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reply to Message</a>
            </div>
            
            <hr>
            <p style='color: #666; font-size: 12px;'>This notification was sent via ExpertHub platform.</p>
        </div>
    </body>
    </html>";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: ExpertHub <noreply@experthub.com>" . "\r\n";
    
    return mail($recipient_email, $subject, $message, $headers);
}

function sendCustomEmail($to_email, $to_name, $subject, $message, $from_name = 'ExpertHub Provider') {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #0077B6;'>Message from $from_name</h2>
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                    " . nl2br(htmlspecialchars($message)) . "
                </div>
                <hr>
                <p style='color: #666; font-size: 12px;'>This message was sent via ExpertHub platform.</p>
            </div>
        </body>
        </html>";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}
?>