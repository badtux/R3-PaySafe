
<?php
require_once 'config/config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$data = json_decode(file_get_contents("php://input"), true);
$subject = "Payment Status Update";
$body = "";
$transactionId = isset($data['transactionId']) ? $data['transactionId'] : '';
$orderId = isset($data['orderId']) ? $data['orderId'] : '';
$status = $data['status'];
$recipientEmail = "piumal0713@gmail.com";  

$amount = isset($_GET['amount']) ? $_GET['amount'] : "1.00";
$currency = isset($_GET['currency']) ? $_GET['currency'] : "USD";

if ($status == 'payment error') {
    $body = "❌ Payment Error\n\nOrder ID: " . $orderId . "\nAmount: " . $amount . "\nCurrency: " . $currency . "\n\nError details: " . print_r($data['error'], true);

} elseif ($status == 'payment error') {
    $body = "⚠️ Payment Canceled\nOrder ID: " . $orderId . "\nAmount: " . $amount . "\nCurrency: " . $currency;

} elseif ($status == 'success') {
    $body = "✅ Payment Successful\n\nOrder ID: " . $orderId . "\nAmount: " . $amount . "\nCurrency: " . $currency;;
}

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
    $mail->addAddress($recipientEmail); 
    foreach (CC_LIST as $cc) {
        $mail->AddCC($cc);
    }
    $mail->isHTML(false);
    $mail->Subject = $subject;
    $mail->Body = $body;

    $mail->send();

    echo "Email sent successfully.";
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}