<?php

require_once('lib/Simplify.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Retrieve POST data
$token = $_POST['simplifyToken'] ?? null;
$name = $_POST['name'] ?? 'Customer';
$reference = $_POST['reference'] ?? 'No reference';
$email = $_POST['email'] ?? '';
$price = isset($_POST['price']) ? floatval($_POST['price']) : 0.0;
$currency_p = strtoupper($_POST['currency'] ?? 'LKR');
$amount_p = $price > 0 ? intval($price * 100) : 0;



while (!$token && $retryCount < $maxRetries) {
    sleep($retryDelay);
    $retryCount++;
   
    $token = $_POST['simplifyToken'] ?? null;
}

// Validate token and email
if (!$token) {
    echo json_encode(['paymentStatus' => 'ERROR', 'message' => 'Token generation timed out. Please try again.']);
    exit;
}

if (empty($email)) {
    echo json_encode(['paymentStatus' => 'ERROR', 'message' => 'No email address provided.']);
    exit;
}

// Set Simplify credentials
Simplify::$publicKey = ($currency_p === 'LKR') ? SMPLY_LKR_PUBKEY : SMPLY_USD_PUBKEY;
Simplify::$privateKey = ($currency_p === 'LKR') ? SMPLY_LKR_PVKEY : SMPLY_USD_PVKEY;

try {
    $payment = Simplify_Payment::createPayment([
        'reference' => $reference,
        'amount' => $amount_p,
        'description' => 'Payment description',
        'currency' => $currency_p,
        'token' => $token,
    ]);

    if ($payment->paymentStatus === 'APPROVED') {
        $transactionId = $payment->id;
        $amountFormatted = number_format($amount_p / 100, 2);

        $notificationMessage = "Your payment for $reference has been Approved. Thank you!";
        $paymentStatus = 'APPROVED';

        error_log("Payment Approved: Transaction ID: $transactionId, Amount: $amountFormatted");

        // Send email
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port = MAIL_PORT;

        $mail->setFrom(MAIL_ADDRESS, MAIL_NAME);
        $mail->addAddress($email);

        $mail->isHTML(false);
        $mail->Subject = "Payment Confirmation - Ref: $reference";
        $mail->Body = "Dear $name,\n\nYour payment of " .
            ($currency_p === 'USD' ? '$' : 'LKR ') . "$amountFormatted has been successfully approved.\n\n" .
            "Transaction ID: $transactionId\n\nThank you for your purchase!";

        try {
            if ($mail->send()) {
                $notificationMessage .= ' Email sent successfully.';
            }
        } catch (Exception $e) {
            error_log("Email Error: " . $e->getMessage());
            $notificationMessage .= " However, email couldn't be sent: " . $mail->ErrorInfo;
        }
    } else {
        $notificationMessage = "Payment Failed: {$payment->paymentStatus}";
        $paymentStatus = 'FAILED';
        error_log("Payment Failed: {$payment->paymentStatus}");
    }
} catch (Exception $e) {
    error_log("Payment Exception: " . $e->getMessage());
    echo json_encode(['paymentStatus' => 'ERROR', 'message' => 'Payment Error: ' . htmlspecialchars($e->getMessage())]);
    exit;
}

echo json_encode([
    'paymentStatus' => isset($paymentStatus) ? $paymentStatus : 'ERROR',
    'message' => htmlspecialchars($notificationMessage),
    'reference' => htmlspecialchars($reference),
    'price' => number_format($price, 2),
    'currency' => htmlspecialchars($currency_p),
]);

exit;
