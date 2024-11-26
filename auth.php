<?php

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

           
            error_log("Payment details: " . print_r($payment, true));

            if ($payment->paymentStatus == 'APPROVED') {
                $notificationMessage = "Your payment for " . htmlspecialchars($reference) . " has been Approved. Thank you!";
                $status = 'APPROVED';

                // Send confirmation email
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
                    $mail->AddCC('viraj.abayarathna@gmail.com');

                    $mail->isHTML(false);
                    $mail->Subject = "Payment Confirmation";
                    $mail->Body = "Dear " . htmlspecialchars($name) . ", reference " . htmlspecialchars($reference) . "\n\nYour payment of " .
                        ($currency == 'USD' ? '$' : 'LKR ') . number_format($price, 2) .
                        " has been successfully approved.\n\nThank you for your purchase!";
                    $sendStatus = $mail->send();

                    $notificationMessage .= ' Email sent successfully.';
                } catch (Exception $e) {
                    $notificationMessage .= ' However, we couldn\'t send a confirmation email: ' . htmlspecialchars($mail->ErrorInfo);
                }
            } else {
                $notificationMessage = 'Payment Failed: ' . htmlspecialchars($payment->paymentStatus);
                $status = 'FAILED';
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
header("Location:/paymentpage.php?status=$status&message=" . urlencode($notificationMessage));
exit();
