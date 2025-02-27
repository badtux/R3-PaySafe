<?php
require_once 'config/config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$data = json_decode(file_get_contents("php://input"), true);

// Get the necessary data from the POST request
$transactionId = isset($data['transactionId'])? $data['transactionId'] : "no set id ";
$orderId = isset($data['orderId']) ? $data['orderId'] : '';
$status = isset($data['status']) ? $data['status'] : '';
$email = isset($data['email']) ? $data['email'] : '';  // Get email from the request

$amount = isset($data['amount']) ? $data['amount'] : '';  
$currency = isset($data['currency']) ? $data['currency'] : ''; 

$subject = "Payment Status Update";
$body = "";

error_log("id====================================$transactionId");


if ($status == 'payment error') {
    $body = '
        <div style="font-family: Arial, sans-serif; color: #721c24; background-color: #f8d7da; padding: 20px; border-radius: 5px; border: 1px solid #f5c6cb;">
            <h2 style="color: #721c24; margin-top: 0;">❌ Payment Error</h2>
            <div style="background-color: white; padding: 15px; border-radius: 4px;">
                <h3 style="margin: 0 0 10px 0;">Order Details</h3>
                <table>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong>Order ID:</strong></td><td>'.$orderId.'</td></tr>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong>Amount:</strong></td><td>'.$amount.' '.$currency.'</td></tr>
                </table>
                <div style="margin-top: 15px; color: #856404; background-color: #fff3cd; padding: 10px; border-radius: 4px;">
                    <h4 style="margin: 0 0 5px 0;">Error Details:</h4>
                    <pre style="margin: 0; font-family: Consolas, monospace;">'.print_r($data['error'], true).'</pre>
                </div>
            </div>
        </div>
    ';
} elseif ($status == 'payment canceled') {  // Changed from duplicate 'payment error'
    $body = '
        <div style="font-family: Arial, sans-serif; color: #856404; background-color: #fff3cd; padding: 20px; border-radius: 5px; border: 1px solid #ffeeba;">
            <h2 style="color: #856404; margin-top: 0;">⚠️ Payment Canceled</h2>
            <div style="background-color: white; padding: 15px; border-radius: 4px;">
                <h3 style="margin: 0 0 10px 0;">Order Details</h3>
                <table>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong>Order ID:</strong></td><td>'.$orderId.'</td></tr>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong>Amount:</strong></td><td>'.$amount.' '.$currency.'</td></tr>
                </table>
            </div>
        </div>
    ';
} elseif ($status == 'success') {
    $body = '
        <div style="font-family: Arial, sans-serif; color: #155724; background-color: #d4edda; padding: 20px; border-radius: 5px; border: 1px solid #c3e6cb;">
            <h2 style="color: #155724; margin-top: 0;">✅ Payment Successful</h2>
            <div style="background-color: white; padding: 15px; border-radius: 4px;">
                <h3 style="margin: 0 0 10px 0;">Order Details</h3>
                <table>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong>Order ID:</strong></td><td>'.$orderId.'</td></tr>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong>Amount:</strong></td><td>'.$amount.' '.$currency.'</td></tr>
                </table>
                <p style="margin: 15px 0 0 0; color: #155724;">Thank you for your payment!</p>
            </div>
        </div>
    ';
}

// Don't forget to set email headers for HTML content
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
error_log("body=============>$body");

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug = 2;
    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure = MAIL_ENCRYPTION;
    $mail->Port = MAIL_PORT;
    $mail->setFrom(MAIL_ADDRESS, MAIL_NAME);
    $mail->addAddress($email);
    foreach (CC_LIST as $cc) {
        $mail->AddCC($cc);
    }
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $body;

    $mail->send();

    echo "Email sent successfully.";
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}