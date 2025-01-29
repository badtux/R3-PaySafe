<?php
//session_start();


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require_once 'config/config.php';
require_once('lib/Simplify.php');

$notificationMessage = '';
$name = isset($_POST['name']) ? $_POST['name'] : 'Customer';
$reference = isset($_POST['reference']) ? $_POST['reference'] : 'No reference';
$email = isset($_POST['email']) ? $_POST['email'] : ''; // Fetch the actual email value
$price = isset($_POST['price']) ? floatval($_POST['price']) : 0.0;
$currency_p = isset($_POST['currency']) ? strtoupper($_POST['currency']) : 'LKR';
$amount_p = $price > 0 ? intval($price * 100) : 0;
$token = !isset($_POST['simplifyToken'])?false:trim($_POST['simplifyToken']);

function sendMail($email, $subject, $body){
    $mail = new PHPMailer(true);
            
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

    foreach (CC_LIST as $cc) {header('Content-Type: application/json; charset=utf-8');
        $mail->AddCC($cc);
    }
    $mail->isHTML(false);
}

function sendOut($status, $notificationMessage, $data=false){
    header('Content-Type: application/json; charset=utf-8');
    $out = [
        'paymentStatus' => $status,
        'status' => $status,
        'message' => $notificationMessage
    ];

    if($data) { $out['data'] = $data; }
    echo json_encode($out);
    exit;
}

try {
    if(!$token) { throw new Exception('Token not found', 101); }

    if ($currency_p == 'LKR') {
        Simplify::$publicKey = SMPLY_LKR_PUBKEY;
        Simplify::$privateKey = SMPLY_LKR_PVKEY;
    } else {
        Simplify::$publicKey = SMPLY_USD_PUBKEY;
        Simplify::$privateKey = SMPLY_USD_PVKEY;
    }

    $payment = Simplify_Payment::createPayment([
        'reference' => $reference,
        'amount' => $amount_p,
        'description' => 'Payment description',
        'currency' => $currency_p,
        'token' => $token,
    ]);

    if ($payment->paymentStatus == 'APPROVED') {
        $transactionId = $payment->id;
        $declineReason = $payment->declineReason;
        $currency = $payment->currency;
        $amount = $payment->amount;
        $getprice = $amount / 100;
        
        $mail->Subject = "PaySafe - Payment Confirmation for Ref- " . htmlspecialchars($reference) . "";
        $mail->Body = "Dear " . htmlspecialchars($name) . ",\n\nYour payment of " .
            ($currency == 'USD' ? '$' : 'LKR ') . number_format($getprice, 2) .
            " has been successfully approved." .
            "Transaction ID: " . htmlspecialchars($transactionId) . "\n\n" .
            "Thank you for your purchase!";

        sendMail($email, $subjet, $body);

        throw new Exception('Payment approved', 201);
    }
    else {
        $transactionId = $payment->id;
        $currency = $payment->currency;
    
        $mail->Subject = "PaySafe - Payment failed  Ref-" . htmlspecialchars($reference) . "\n\n";
        $mail->Body = "Dear " . htmlspecialchars($name) . ",\n\nWe regret to inform you that your payment of " .
            ($currency == 'USD' ? '$' : 'LKR ') . number_format($price, 2) . " was declined ." . "\n" .
            "Transaction ID: " . htmlspecialchars($transactionId) . "\n" .
            "Please try again or contact support for assistance.";

        sendMail($email, $subjet, $body);
        throw new Exception('Payment failed', 401);
    }
}

catch(Simplify_BadRequestException | Simplify_ApiConnectionException | Exception $e){
    switch($e->getCode()) {
        case 201:
            sendOut('APPROVED', 'Your payment for ' . htmlspecialchars($reference) . ' has been Approved. Thank you!');
            break;

        case 401:
            sendOut('FAILED', 'Payment Failed: ' . htmlspecialchars($payment->paymentStatus));
            break;

        default:
            sendOut('ERROR', 'Payment Error: ' . htmlspecialchars($e->getMessage()));
            break;
    }
}