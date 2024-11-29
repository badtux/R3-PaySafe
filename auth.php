<?php
//session_start();
require_once('lib/Simplify.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$notificationMessage = '';
$name = isset($_POST['name']) ? $_POST['name'] : 'Customer';
$reference = isset($_POST['reference']) ? $_POST['reference'] : 'No reference';
$email = isset($_POST['email']) ? $_POST['email'] : ''; // Fetch the actual email value
$price = isset($_POST['price']) ? floatval($_POST['price']) : 0.0;
$currency = isset($_POST['currency']) ? strtoupper($_POST['currency']) : 'LKR';
$amount = $price > 0 ? intval($price * 100) : 0;

if (isset($_POST['simplifyToken'])) {
    $token = $_POST['simplifyToken'];

    if ($currency == 'LKR') {
        Simplify::$publicKey = SMPLY_LKR_PUBKEY;
        Simplify::$privateKey = SMPLY_LKR_PVKEY;
    } else {
        Simplify::$publicKey = SMPLY_USD_PUBKEY;
        Simplify::$privateKey = SMPLY_USD_PVKEY;
    }

    if (empty($email)) {
        $notificationMessage = 'No email address provided.';
        $status = 'ERROR';
    } else {
        try {
            $payment = Simplify_Payment::createPayment(array(
                'reference' => $reference,
                'amount' => $amount,
                'description' => 'Payment description',
                'currency' => $currency,
                'token' => $token,
            ));

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

                $mail->isHTML(false);

                if ($payment->paymentStatus == 'APPROVED') {
                    $transactionId = $payment->id;
                    $declineReason = $payment->declineReason;
                    $notificationMessage = "Your payment for " . htmlspecialchars($reference) . " has been Approved. Thank you!";
                    $status = 'APPROVED';

                    // Approved payment email
                    $mail->Subject = "PaySafe - Payment Confirmation for Ref- " . htmlspecialchars($reference) . "";
                    $mail->Body = "Dear " . htmlspecialchars($name) . ",\n\nYour payment of " .
                        ($currency == 'USD' ? '$' : 'LKR ') . number_format($price, 2) .
                        " has been successfully approved." .
                        "Transaction ID: " . htmlspecialchars($transactionId) . "\n\n" .
                        "Thank you for your purchase!";
                } else {
                    $notificationMessage = 'Payment Failed: ' . htmlspecialchars($payment->paymentStatus);
                    $status = 'FAILED';
                    $transactionId = $payment->id;


                    // Declined payment email
                    $mail->Subject = "PaySafe - Payment failed  Ref-" . htmlspecialchars($reference) . "\n\n";
                    $mail->Body = "Dear " . htmlspecialchars($name) . ",\n\nWe regret to inform you that your payment of " .
                        ($currency == 'USD' ? '$' : 'LKR ') . number_format($price, 2) . " was declined ." . "\n" .
                        "Transaction ID: " . htmlspecialchars($transactionId) . "\n" .
                        "Please try again or contact support for assistance.";
                }

                $mail->send();
                $notificationMessage .= ' Email sent successfully.';
            } catch (Exception $e) {
                $notificationMessage .= ' However, we couldn\'t send a confirmation email: ' . htmlspecialchars($mail->ErrorInfo);
            }
        } catch (Exception $e) {
            $notificationMessage = 'Payment Error: ' . htmlspecialchars($e->getMessage());
            $status = 'ERROR';
        }
    }
} else {
    $notificationMessage = 'No Payment Token Found. Please try again.';
    $status = 'ERROR';
}

// Redirect back to payment page with status and message
header("Location: " . BASE_PATH . "?status=$status&message=" . urlencode($notificationMessage));


exit();
