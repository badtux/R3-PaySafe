<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("Session ID: " . session_id());

require 'vendor/autoload.php';
require_once('config/config.php');
//require_once('config/config.sample.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;


if (isset($_POST['email'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    error_log("Email received: " . $email);
    $_SESSION['email'] = $email;
} else {
    error_log("No email in POST");
    if (isset($_SESSION['email'])) {
        $email = $_SESSION['email'];
        error_log("Email retrieved from session: $email");
    } else {
        $email = 'example@example.com';
        error_log("No email in POST or session, using fallback: $email");
    }
}

 error_log("UUID in session: " . ($_SESSION['uuid'] ?? 'not set'));


$orderId = $_SESSION['orderId'] ?? 'no-order-id';
$currency = $_SESSION['currency'] ?? 'USD';
$uuid = $_SESSION['uuid'] ?? null;

$database_url = DATABASE_URL;
$collection = COLLECTION;
$database = DB;


$merchantId = MERCHANT_ID;
$apiUserName = API_USERNAME;
$apiPassword = API_PASSWORD;


error_log($orderId);
error_log($merchantId);

$gatewayUrl = "https://test-seylan.mtf.gateway.mastercard.com/api/rest/version/67/merchant/$merchantId/order/$orderId";
error_log('-------------' . $gatewayUrl);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $gatewayUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, "merchant.$merchantId:$apiPassword");
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$paymentStatus = "";
$emailMessage = "";

if ($httpCode == 200) {
    $data = json_decode($response, true);

    if (!empty($data)) {
        $paymentStatus = htmlspecialchars($data['result'] ?? 'N/A');
        $transactionId = $data['authentication']['3ds']['transactionId'] ?? 'not-set';
        $nameOnCard = $data['sourceOfFunds']['provided']['card']['nameOnCard'] ?? 'not-set';
        $cardNumber     = $data['sourceOfFunds']['provided']['card']['number'] ?? 'N/A';
        $merchant = $data['merchant'] ?? 'not-set';
        $device = $data['device'] ?? [];
        $cardBrand = $data['sourceOfFunds']['provided']['card']['brand'] ?? 'N/A';
        $orderId = $data['id'] ?? $orderId;
        $fundingMethord = $data['sourceOfFunds']['provided']['card']['fundingMethod'] ?? 'N/A';
        $lastUpdated = $data['lastUpdatedTime'] ? new UTCDateTime(strtotime($data['lastUpdatedTime']) * 1000) : new UTCDateTime();
        $amount = isset($data['amount']) ? number_format((float)$data['amount'], 2, '.', '') : '0.00';
        $currency = htmlspecialchars($data['currency'] ?? 'N/A');
        $status = strtolower($data['result'] ?? '');
        $mailStatus = match ($status) {
            'success' => 'success',
            'error' => 'payment error',
            'canceled' => 'payment canceled',
            default => 'unknown',
        };
        error_log("Response: $response");
        error_log("uuid:$uuid");
        try {
            $client = new Client($database_url);
            $collection = $client->$database->$collection;

            $updateData = [
                'paymentStatus' => $paymentStatus,
                'transactionId' => $transactionId,
                'nameOnCard' => $nameOnCard,
                'merchantId' => $merchant,
                'device' => $device,
                'cardBrand' => $cardBrand,
                'orderId' => $orderId,
                'fundingMethord' => $fundingMethord,
                'email' => $email,
                'updatedAt' => $lastUpdated,
                'cardNumber' => $cardNumber,
            ];
            if (!$uuid) {
                error_log("UUID not set in session! Cannot update MongoDB.");
            } else {
                $collection->updateOne(
                    ['uuid' => $uuid],
                    ['$set' => $updateData]
                );
                error_log("set: " . json_encode($updateData));
            }
        } catch (Exception $e) {
            error_log("MongoDB Update Error: " . $e->getMessage());
        }

        $subject = "Payment Status Update for - OID:$orderId ";
        if ($mailStatus == 'payment error') {
            $body = '
            <div style="font-family: Arial, sans-serif; color: #721c24; background-color: #f8d7da; padding: 20px; border-radius: 5px; border: 1px solid #f5c6cb;">
                <h2 style="color: #721c24; margin-top: 0;">❌ Payment Error </h2>
                <div style="background-color: white; padding: 15px; border-radius: 4px;">
                    <h3 style="margin: 0 0 10px 0;">Order Details</h3>
                    <table>
                        <tr><td style="padding: 5px 10px 5px 0;"><strong>Order ID:</strong></td><td>' . htmlspecialchars($orderId) . '</td></tr>
                        <tr><td style="padding: 5px 10px 5px 0;"><strong>Transaction ID:</strong></td><td>' . htmlspecialchars($transactionId) . '</td></tr>
                        <tr><td style="padding: 5px 10px 5px 0;"><strong> Card Number:</strong></td><td>' . htmlspecialchars($cardNumber) . '</td></tr>
                        <tr><td style="padding: 5px 10px 5px 0;"><strong> Card Holder Name:</strong></td><td>' . htmlspecialchars($nameOnCard) . '</td></tr>
                        <tr><td style="padding: 5px 10px 5px 0;"><strong>Amount:</strong></td><td>' . htmlspecialchars($amount) . ' ' . htmlspecialchars($currency) . '</td></tr>
                        
                    </table>
                    <div style="margin-top: 15px; color: #856404; background-color: #fff3cd; padding: 10px; border-radius: 4px;">
                        <h4 style="margin: 0 0 5px 0;">Error Details:</h4>
                        <pre style="margin: 0; font-family: Consolas, monospace;">' . htmlspecialchars($data['error'] ?? 'Unknown error') . '</pre>
                    </div>
                </div>
            </div>';
        } elseif ($mailStatus == 'payment canceled') {
            $body = '
            <div style="font-family: Arial, sans-serif; color: #856404; background-color: #fff3cd; padding: 20px; border-radius: 5px; border: 1px solid #ffeeba;">
                <h2 style="color: #BB6E2F; margin-top: 0;">⚠️ Payment Canceled </h2>
                <div style="background-color: white; padding: 15px; border-radius: 4px;">
                    <h3 style="margin: 0 0 10px 0;">Order Details</h3>
                    <table>
                        <tr><td style="padding: 5px 10px 5px 0;"><strong>Order ID:</strong></td><td>' . htmlspecialchars($orderId) . '</td></tr>
                        <tr><td style="padding: 5px 10px 5px 0;"><strong>Transaction ID:</strong></td><td>' . htmlspecialchars($transactionId) . '</td></tr>
                         <tr><td style="padding: 5px 10px 5px 0;"><strong> Card Number:</strong></td><td>' . htmlspecialchars($nameOnCard) . '</td></tr>
                          <tr><td style="padding: 5px 10px 5px 0;"><strong>  Card Holder Name:</strong></td><td>' . htmlspecialchars($cardNumber) . '</td></tr>
                        <tr><td style="padding: 5px 10px 5px 0;"><strong>Amount:</strong></td><td>' . htmlspecialchars($amount) . ' ' . htmlspecialchars($currency) . '</td></tr>
                        
                         
                    </table>
                </div>
            </div>';
        } elseif ($mailStatus == 'success') {
            $body = '
            <div style="font-family: Arial, sans-serif; color: #155724; background-color: #d4edda; padding: 20px; border-radius: 5px; border: 1px solid #c3e6cb;">
                <h2 style=" margin-right:10 color:#155724; margin-top: 0;">✅ Payment Successful <img src=https://www.seylan.lk/images/web/icons/logo-2025.png alt="Bank Icon" style="width: 50px; height: 30px; vertical-align: middle;"></h2>
                <div style="background-color: white; padding: 15px; border-radius: 4px;">
                    <h3 style="margin: 0 0 10px 0;">Order Details</h3>
                    <table>
                        <tr><td style="padding: 5px 10px 5px 0;"><strong>Order ID:</strong></td><td>' . htmlspecialchars($orderId) . '</td></tr>
                        <tr><td style="padding: 5px 10px 5px 0;"><strong>Transaction ID:</strong></td><td>' . htmlspecialchars($transactionId) . '</td></tr>
                         <tr><td style="padding: 5px 10px 5px 0;"><strong> Card Number:</strong></td><td>' . htmlspecialchars($cardNumber) . '</td></tr>
                          <tr><td style="padding: 5px 10px 5px 0;"><strong>  Card Holder Name:</strong></td><td>' . htmlspecialchars($nameOnCard) . '</td></tr>
                        <tr><td style="padding: 5px 10px 5px 0;"><strong>Amount:</strong></td><td>' . htmlspecialchars($amount) . ' ' . htmlspecialchars($currency) . '</td></tr>
                    </table>
                    <p style="margin: 15px 0 0 0; color: #155724;">Thank you for  Donations to HelpAge.</p>
                </div>
            </div>';
        } else {
            $body = '<p>Unknown payment status: ' . htmlspecialchars($status) . '</p>';
        }

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
                $mail->addCC($cc);
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
        .bg-gradient-red-orange {
            background: linear-gradient(135deg, #dc2626 0%, #ea580c 100%);
        }

        .bg-gradient-red-orange-light {
            background: linear-gradient(135deg, #fef2f2 0%, #fff7ed 100%);
        }

        .bg-gradient-red-orange-hover {
            background: linear-gradient(135deg, #b91c1c 0%, #c2410c 100%);
        }

        .text-red-orange {
            color: #ea580c;
        }

        .border-red-orange {
            border-color: #ea580c;
        }

        .shadow-red-orange {
            box-shadow: 0 10px 15px -3px rgba(220, 38, 38, 0.1), 0 4px 6px -2px rgba(220, 38, 38, 0.05);
        }

        .hover-shadow-red-orange:hover {
            box-shadow: 0 20px 25px -5px rgba(220, 38, 38, 0.1), 0 10px 10px -5px rgba(220, 38, 38, 0.04);
        }

        #payment-status.success {
            color: #155724;
            /* background-color: #d4edda; */
            border: 1px solid #c3e6cb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 1rem;
        }

        #payment-status.error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 1rem;
        }
    </style>
</head>

<body class="bg-gradient-red-orange-light min-h-screen flex items-center justify-center p-4">
    <div id="main-container" class="bg-white rounded-2xl shadow-red-orange transition-all duration-300 hover:shadow-red-orange w-full max-w-lg overflow-hidden">
        <div class="bg-gradient-red-orange p-6 text-center">
            <img src="https://www.helpagesl.org/wp-content/uploads/2016/05/logo.png" alt="Logo" class="w-20 h-10 mx-auto mb-2 shadow-xl">
            <h1 class="text-2xl font-bold text-white">Secure Payment</h1>
            <p class="text-white text-xs">Protected by Selan Bank</p>
        </div>
        <div id="payment-status" class="mt-6 text-center text-lg font-semibold <?php echo ($paymentStatus === 'SUCCESS' ? 'success' : 'error'); ?>">
            Payment Status: <?php echo $paymentStatus; ?><br>
            <?php echo $emailMessage; ?>
        </div>
    <div class="flex justify-center space-x-10">
 
    <div class="flex flex-col items-center w-full space-y-6">
        <button 
            onclick="window.location.href='https://www.helpagesl.org/'" 
            id="return-to-merchant-btn" 
            class="bg-gradient-red-orange hover:bg-gradient-red-orange-hover text-white font-bold py-4 px-6 mt-2 rounded-xl transition-all duration-300 transform hover:scale-[1.02] shadow-lg hover:shadow-red-200 flex items-center justify-center space-x-2 w-1/2 mx-auto">
            Return to Merchant
        </button>

        <button 
            onclick="window.location.href='pdf.php'" 
            class="text-red-400 underline hover:text-red-800 transition-all duration-300 transform hover:scale-[1.02] mt-4 w-1/2 mx-auto">
            Download Receipt 
        </button>
    </div>
</div>

        <div class="mt-3 mb-6 flex items-center justify-center text-sm text-gray-500">
            <div class="flex items-center">
                <i class='bx bx-shield-quarter text-green-500'></i>
                <span class="mr-2">256-bit SSL Secured Connection</span>
            </div>
            <div class="flex items-center space-x-3">
                <img src="assets/card_logo.png" alt="another logo" class="h-10">
                <img src="assets/bank_logo.png" alt="bank logo" class="h-10">

            </div>
        </div>
    </div>
</body>

</html>