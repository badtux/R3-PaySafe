<?php
session_start();
require_once("../lib/Simplify.php");
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

Simplify::$publicKey = 'sbpb_NjU0NWMyMjMtMzVmYi00ZWVjLWI0NDItN2I4MjljZWJiM2I0';
Simplify::$privateKey = '5Hsh1LbHPktNOcWZ0ZBwUQADlyquDfSmiPMwX7qxrzd5YFFQL0ODSXAOkNtXTToq';

$notificationMessage = '';

if (isset($_POST['simplifyToken'])) {
    $token = $_POST['simplifyToken'];


    if (isset($_POST['price'])) {
        $price = $_POST['price'];
        $amount = intval($price * 100);
    } else {
        $amount = 1000;
    }


    $currency = isset($_POST['currency']) ? $_POST['currency'] : 'USD';


    $reference = uniqid();
    $email = $_POST['email'];

    if (empty($email)) {
        $notificationMessage = 'No email address provided.';
        $status = 'ERROR';
    } else {
        try {

            $payment = Simplify_Payment::createPayment(array(
                'reference' => $reference,
                'amount' => $amount,
                'description' => 'payment description',
                'currency' => $currency,
                'token' => $token,
            ));


            if ($payment->paymentStatus == 'APPROVED') {
                $notificationMessage = 'Payment Approved! Thank you for your purchase!';
                $status = 'APPROVED';


                $mail = new PHPMailer(true);
                try {
                    define('MAIL_HOST', 'digitable.io');
                    define('MAIL_PORT', '465');
                    define('MAIL_USERNAME', 'no-reply@digitable.io');
                    define('MAIL_PASSWORD', "=pvNlO)5=atu");
                    define('MAIL_ENCRYPTION', 'ssl');
                    define('MAIL_FROM_ADDRESS', 'no-reply@digitable.io');

                    $mail->addAddress($email);

                    $mail->isSMTP();
                    $mail->Host = MAIL_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = MAIL_USERNAME;
                    $mail->Password = MAIL_PASSWORD;
                    $mail->SMTPSecure = MAIL_ENCRYPTION;
                    $mail->Port = MAIL_PORT;

                    $mail->isHTML(false);
                    $mail->Subject = "Payment Confirmation";
                    $mail->Body = "Dear Customer,\n\nYour payment of " . ($currency == 'USD' ? '$' : '₹') . $price . " has been successfully approved.\n\nThank you for your purchase!";

                    $mail->send();
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

header("Location:../paymentpage.php?status=$status&message=" . urlencode($notificationMessage));
exit();
