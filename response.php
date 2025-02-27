<?php
// Start the session
session_start();

require_once 'config/config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$merchantId = "TEST9170372718";
$apiPassword = "9561cde89b146e22afd2dbec7d145a4f";

// Retrieve orderId from session, fallback to a default if not set
$orderId = isset($_SESSION['orderId']) ? $_SESSION['orderId'] : "no order id";
$gatewayUrl = "https://nationstrustbankplc.gateway.mastercard.com/api/rest/version/81/merchant/$merchantId/order/$orderId";

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $gatewayUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, "merchant.$merchantId:$apiPassword");
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Variables to hold status and email message
$paymentStatus = "";
$emailMessage = "";

if ($httpCode == 200) {
    $data = json_decode($response, true);

    if ($data) {
        $paymentStatus = htmlspecialchars($data['result'] ?? 'N/A');

        // Prepare email data from API response
        $transactionId = $data['authentication']['3ds']['transactionId'] ?? "no set id";
        $orderId = $data['id'] ?? '';
        $amount = $data['amount'] ?? '';
        $currency = $data['currency'] ?? '';
        $status = strtolower($data['result'] ?? '');
        $email = "recipient@example.com"; // Replace with actual recipient email

        // Map API status to mail statuses
        if ($status === 'success') {
            $mailStatus = 'success';
        } elseif ($status === 'error') {
            $mailStatus = 'payment error';
        } elseif ($status === 'canceled') {
            $mailStatus = 'payment canceled';
        } else {
            $mailStatus = 'unknown';
        }

        $subject = "Payment Status Update";
        $body = "";

        // Generate email body based on status
        if ($mailStatus == 'payment error') {
            $body = '
    <div style="font-family: Arial, sans-serif; color: #721c24; background-color: #f8d7da; padding: 20px; border-radius: 5px; border: 1px solid #f5c6cb;">
        <h2 style="color: #721c24; margin-top: 0;">❌ Payment Error <img src="assets/banklogo1.png" alt="Error Icon" style="width: 24px; height: 24px; vertical-align: middle;"></h2>
        <div style="background-color: white; padding: 15px; border-radius: 4px;">
            <h3 style="margin: 0 0 10px 0;">Order Details</h3>
            <table>
                <tr><td style="padding: 5px 10px 5px 0;"><strong>Order ID:</strong></td><td>' . htmlspecialchars($orderId) . '</td></tr>
                <tr><td style="padding: 5px 10px 5px 0;"><strong>Transaction ID:</strong></td><td>' . htmlspecialchars($transactionId) . '</td></tr>
                <tr><td style="padding: 5px 10px 5px 0;"><strong>Amount:</strong></td><td>' . htmlspecialchars($amount) . ' ' . htmlspecialchars($currency) . '</td></tr>
            </table>
            <div style="margin-top: 15px; color: #856404; background-color: #fff3cd; padding: 10px; border-radius: 4px;">
                <h4 style="margin: 0 0 5px 0;">Error Details:</h4>
                <pre style="margin: 0; font-family: Consolas, monospace;">' . htmlspecialchars($data['error'] ?? 'Unknown error') . '</pre>
            </div>
        </div>
    </div>
';
        } elseif ($mailStatus == 'payment canceled') {
            $body = '
                <div style="font-family: Arial, sans-serif; color: #856404; background-color: #fff3cd; padding: 20px; border-radius: 5px; border: 1px solid #ffeeba;">
                 <h2 style="color: #BB6E2FFF; margin-top: 0;">⚠️ Payment Canceled <img src="assets/banklogo1.png" alt="Error Icon" style="width: 24px; height: 24px; vertical-align: middle;"></h2>
                    <div style="background-color: white; padding: 15px; border-radius: 4px;">
                        <h3 style="margin: 0 0 10px 0;">Order Details</h3>
                        <table>
                            <tr><td style="padding: 5px 10px 5px 0;"><strong>Order ID:</strong></td><td>' . htmlspecialchars($orderId) . '</td></tr>
                               <tr><td style="padding: 5px 10px 5px 0;"><strong>Transaction ID:</strong></td><td>' . htmlspecialchars($transactionId) . '</td></tr>
                            <tr><td style="padding: 5px 10px 5px 0;"><strong>Amount:</strong></td><td>' . htmlspecialchars($amount) . ' ' . htmlspecialchars($currency) . '</td></tr>
                        </table>
                    </div>
                </div>
            ';
        } elseif ($mailStatus == 'success') {
            $body = '
                <div style="font-family: Arial, sans-serif; color: #155724; background-color: #d4edda; padding: 20px; border-radius: 5px; border: 1px solid #c3e6cb;">
                  <h2 style="color:#155724; margin-top: 0;">✅ Payment Successful <img src="assets/banklogo1.png" alt="Error Icon" style="width: 24px; height: 24px; vertical-align: middle;"></h2>
                    <div style="background-color: white; padding: 15px; border-radius: 4px;">
                        <h3 style="margin: 0 0 10px 0;">Order Details</h3>
                        <table>
                            <tr><td style="padding: 5px 10px 5px 0;"><strong>Order ID:</strong></td><td>' . htmlspecialchars($orderId) . '</td></tr>
                               <tr><td style="padding: 5px 10px 5px 0;"><strong>Transaction ID:</strong></td><td>' . htmlspecialchars($transactionId) . '</td></tr>
                            <tr><td style="padding: 5px 10px 5px 0;"><strong>Amount:</strong></td><td>' . htmlspecialchars($amount) . ' ' . htmlspecialchars($currency) . '</td></tr>
                        </table>
                        <p style="margin: 15px 0 0 0; color: #155724;">Thank you for your payment With Malkey Rent A Car</p>
                    </div>
                </div>
            ';
        } else {
            $body = '<p>Unknown payment status: ' . htmlspecialchars($status) . '</p>';
        }

        // Send email using PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->SMTPDebug = 0;
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
            $emailMessage = "Email sent successfully.";
        } catch (Exception $e) {
            $emailMessage = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        $paymentStatus = "Unable to decode response";
    }
} else {
    $paymentStatus = "Error retrieving order details (HTTP Code: $httpCode)";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status</title>
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Optional: Add custom styles if needed */
        #payment-status.success { color: #155724; }
        #payment-status.error { color: #721c24; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen flex items-center justify-center p-4">
    <div id="main-container" class="bg-white rounded-2xl shadow-2xl transition-all duration-300 hover:shadow-xl w-full max-w-lg overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 text-center">
            <img src="https://d8asu6slkrh4m.cloudfront.net/2013/04/malkey-logo.png" alt="Logo" class="w-40 h-19 mx-auto mb-2 filter brightness-0 invert">
            <h1 class="text-2xl font-bold text-blue-100">Secure Payment</h1>
            <p class="text-blue-100 text-sm">Protected by Nations Trust Bank</p>
        </div>
        <div id="main_2" class="hidden">
            <!-- Form hidden by default since this is the response page -->
            <div class="px-6 pt-8">
                <div class="space-y-6 mb-8">
                    <!-- Form fields omitted as they're not needed here -->
                </div>
                <button onclick="validateAndProceed()" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] shadow-lg hover:shadow-blue-200 flex items-center justify-center space-x-2">
                    <i class='bx bx-lock-alt text-xl'></i>
                    <span>Proceed to Secure Payment</span>
                </button>
            </div>
        </div>
        <div id="payment-status" class="mt-6 text-center text-lg font-semibold <?php echo ($paymentStatus === 'SUCCESS' ? 'success' : 'error'); ?>">
            Payment Status: <?php echo $paymentStatus; ?><br>
            <?php echo $emailMessage; ?>
        </div>

        <div class="flex justify-center">
            <button onclick="window.location.href='https://www.malkey.lk/'" id="return-to-merchant-btn" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-4 px-6 mt-2 rounded-xl transition-all duration-300 transform hover:scale-[1.02] shadow-lg hover:shadow-blue-200 flex items-center justify-center space-x-2">
                Return to Merchant
            </button>
        </div>

        <div class="mt-3 mb-6 flex items-center justify-center text-sm text-gray-500">
            <div class="flex items-center">
                <i class='bx bx-shield-quarter text-green-500'></i>
                <span class="mr-2">256-bit SSL Secured Connection</span>
            </div>
            <div>
                <img src="assets/card.png" alt="bank logo" class="h-10">
            </div>
        </div>
    </div>

    <script>
        // No need for validateAndProceed since this is the response page
        document.getElementById('payment-status').classList.remove('hidden');
        document.getElementById('return-to-merchant-btn').classList.remove('hidden');
    </script>
</body>
</html>