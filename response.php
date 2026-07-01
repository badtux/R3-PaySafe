<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'vendor/autoload.php';
require_once('config/config.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;


$email = isset($_SESSION['email']) ? filter_var($_SESSION['email'], FILTER_SANITIZE_EMAIL) : '';
if ($email === '') {
    $uuidFromSession = $_SESSION['uuid'] ?? null;
    if (!empty($uuidFromSession)) {
        try {
            $mongoClient = new Client(DATABASE_URL);
            $dbName = DB;
            $collName = COLLECTION;
            $coll = $mongoClient->$dbName->$collName;
            $doc = $coll->findOne(['uuid' => $uuidFromSession]);
            if ($doc && !empty($doc['email']) && filter_var($doc['email'], FILTER_VALIDATE_EMAIL)) {
                $email = filter_var($doc['email'], FILTER_SANITIZE_EMAIL);
                error_log("Email loaded from MongoDB for uuid $uuidFromSession: $email");
            } else {
                error_log("No valid email found in MongoDB for uuid $uuidFromSession");
            }
        } catch (Throwable $e) {
            error_log('Error fetching email from MongoDB for uuid ' . $uuidFromSession . ': ' . $e->getMessage());
        }
    }

    // fallback if still empty
    if ($email === '') {
        $email = 'example@example.com';
        error_log("No email in session or MongoDB, using fallback: $email");
    }
} else {
    error_log("Email retrieved from session: $email");
}

// Store sanitized email back into session so other pages can access it
$_SESSION['email'] = $email;

$orderId = $_SESSION['orderId'] ?? 'no-order-id';
$currency = $_SESSION['currency'] ?? 'USD';
$uuid = $_SESSION['uuid'] ?? null;
    $database_url = DATABASE_URL;
    $collection = COLLECTION;
    $database = DB;

if ($currency == 'LKR') {
    $merchantId = MERCHANT_ID_LKR;
    $apiUserName = API_USERNAME_LKR;
    $apiPassword = API_PASSWORD_LKR;
} else {
    $merchantId = MERCHANT_ID_USD;
    $apiUserName = API_USERNAME_USD;
    $apiPassword = API_PASSWORD_USD;
}
error_log($orderId);
error_log($merchantId);

$gatewayUrl = "https://cbcmpgs.gateway.mastercard.com/api/rest/version/100/merchant/$merchantId/order/$orderId";
error_log('-------------'.$gatewayUrl);

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

// Used for UI output (avoid showing SUCCESS when capture/payment failed)
$isPaidSuccess = false;
$uiStatusText = 'N/A';

if ($httpCode == 200) {
    $data = json_decode($response, true);

    if (!empty($data)) {
        $paymentStatus = htmlspecialchars($data['result'] ?? 'N/A');

        // Prefer values from the PAYMENT transaction (more reliable than top-level fields).
        $paymentTxn = null;
        if (!empty($data['transaction']) && is_array($data['transaction'])) {
            foreach ($data['transaction'] as $txn) {
                $type = strtoupper($txn['transaction']['type'] ?? '');
                if ($type === 'PAYMENT') {
                    $paymentTxn = $txn;
                    break;
                }
            }
        }

        // Determine payment method/type early (CARD vs UNION_PAY)
        $sourceType = strtoupper((string)($data['sourceOfFunds']['type'] ?? ''));

        // Transaction ID rules:
        // - CARD (Visa/Mastercard/Amex): use top-level 3DS transactionId when available
        // - UNION_PAY: use acquirer transactionId (e.g., TESTMALKEYRENLKRxxxx1)
        $top3dsTxnId = $data['authentication']['3ds']['transactionId'] ?? null;
        $acquirerTxnId = $paymentTxn['transaction']['acquirer']['transactionId'] ?? null;
        $receiptTxnId = $paymentTxn['transaction']['receipt'] ?? null;
        $mpgsTxnId = $paymentTxn['transaction']['id'] ?? null;

        if ($sourceType === 'CARD' && !empty($top3dsTxnId)) {
            $transactionId = $top3dsTxnId;
        } elseif ($sourceType === 'UNION_PAY' && !empty($acquirerTxnId)) {
            $transactionId = $acquirerTxnId;
        } else {
            // Fallback (keeps older behavior)
            $transactionId = $acquirerTxnId
                ?? $receiptTxnId
                ?? $mpgsTxnId
                ?? ($data['transaction'][0]['transaction']['id'] ?? null)
                ?? ($top3dsTxnId ?? 'not-set');
        }

        // Name on card may be missing (e.g., UnionPay). Fallback to customer name if present.
        $nameOnCard = $data['sourceOfFunds']['provided']['card']['nameOnCard']
            ?? $paymentTxn['sourceOfFunds']['provided']['card']['nameOnCard']
            ?? trim((string)(($data['customer']['firstName'] ?? '') . ' ' . ($data['customer']['lastName'] ?? '')));

        // Last-resort fallback: derive something readable from the email local-part.
        if (!is_string($nameOnCard) || trim($nameOnCard) === '') {
            $fallbackName = '';
            if (!empty($email) && is_string($email) && str_contains($email, '@')) {
                $local = explode('@', $email, 2)[0];
                $local = str_replace(['.', '_', '-'], ' ', $local);
                $fallbackName = trim($local);
            }
            $nameOnCard = $fallbackName !== '' ? $fallbackName : 'not-set';
        }

        $cardNumber = $data['sourceOfFunds']['provided']['card']['number']
            ?? $paymentTxn['sourceOfFunds']['provided']['card']['number']
            ?? 'N/A';

        $merchant = $data['merchant'] ?? 'not-set';
        $device = $data['device'] ?? [];
        $cardBrand = $data['sourceOfFunds']['provided']['card']['brand']
            ?? $paymentTxn['sourceOfFunds']['provided']['card']['brand']
            ?? 'N/A';

        $orderId = $data['id'] ?? $orderId;
        $fundingMethord = $data['sourceOfFunds']['provided']['card']['fundingMethod']
            ?? $paymentTxn['sourceOfFunds']['provided']['card']['fundingMethod']
            ?? 'N/A';

        $lastUpdated = !empty($data['lastUpdatedTime'])
            ? new UTCDateTime(strtotime($data['lastUpdatedTime']) * 1000)
            : new UTCDateTime();
        $amount = isset($data['amount']) ? number_format((float)$data['amount'], 2, '.', '') : '0.00';
        $currency = htmlspecialchars($data['currency'] ?? 'N/A');

        // Decide success/error based on CAPTURE + payment approval (not only top-level result).
        $orderStatus = strtoupper((string)($data['status'] ?? ''));
        $topResult = strtoupper((string)($data['result'] ?? ''));
        $capturedAmount = (float)($data['totalCapturedAmount'] ?? 0);
        $authorizedAmount = (float)($data['totalAuthorizedAmount'] ?? 0);

        // Expose capture info for DB
        $captureAmountForDb = $capturedAmount;
        $isCaptured = ($orderStatus === 'CAPTURED') && ($capturedAmount > 0);

        $paymentGatewayCode = strtoupper((string)($paymentTxn['response']['gatewayCode'] ?? ''));
        $paymentResult = strtoupper((string)($paymentTxn['result'] ?? ''));

        $isApprovedPayment = ($paymentResult === 'SUCCESS') && ($paymentGatewayCode === 'APPROVED');

        // Some flows may be AUTHORIZED without capture; keep this as non-success unless you want to treat as success.
        $isAuthorizedOnly = ($orderStatus === 'AUTHORIZED') && ($authorizedAmount > 0) && !$isCaptured;

        $isPaidSuccess = ($topResult === 'SUCCESS') && ($isCaptured || $isApprovedPayment);

        $mailStatus = $isPaidSuccess ? 'success' : 'payment error';
        if (in_array($topResult, ['CANCELLED', 'CANCELED'], true)) {
            $mailStatus = 'payment canceled';
            $isPaidSuccess = false;
        }

        // UI message should reflect actual final state.
        $uiStatusText = match ($mailStatus) {
            'success' => 'SUCCESS',
            'payment canceled' => 'CANCELED',
            default => 'FAIL',
        };

        // Persist a final status based on actual payment outcome (capture/approval), not only gateway top-level result.
        $finalPaymentStatus = $uiStatusText;
        $gatewayResult = $paymentStatus;

        error_log('Response: ' . json_encode($data));
        error_log("uuid:$uuid");
        try {
            $client = new Client($database_url);
            $collection = $client->$database->$collection;

            // Build attempts array from gateway transaction[] (store only PAYMENT records)
            $attempts = [];
            if (!empty($data['transaction']) && is_array($data['transaction'])) {
                foreach ($data['transaction'] as $txn) {
                    $txnType = strtoupper((string)($txn['transaction']['type'] ?? ''));
                    if ($txnType !== 'PAYMENT') {
                        continue;
                    }

                    $attemptOrderStatus = strtoupper((string)($txn['order']['status'] ?? ''));
                    $attemptCapturedAmount = (float)($txn['order']['totalCapturedAmount'] ?? 0);
                    $attemptResultRaw = strtoupper((string)($txn['result'] ?? ''));
                    $attemptIsCaptured = ($attemptOrderStatus === 'CAPTURED') && ($attemptCapturedAmount > 0);

                    // Derive final result for this PAYMENT attempt.
                    // If capturedAmount is 0, treat as FAIL even if gateway says SUCCESS.
                    $attemptFinalResult = $attemptIsCaptured ? 'SUCCESS' : 'FAIL';
                    if ($attemptResultRaw === 'FAILURE') {
                        $attemptFinalResult = 'FAIL';
                    }

                    $attempts[] = [
                        'type' => 'PAYMENT',
                        'finalResult' => $attemptFinalResult,
                        'resultRaw' => $attemptResultRaw !== '' ? $attemptResultRaw : 'UNKNOWN',
                        'gatewayCode' => (string)($txn['response']['gatewayCode'] ?? ''),
                        'acquirerCode' => (string)($txn['response']['acquirerCode'] ?? ''),
                        'acquirerMessage' => (string)($txn['response']['acquirerMessage'] ?? ''),
                        'amount' => (float)($txn['transaction']['amount'] ?? ($txn['order']['amount'] ?? 0)),
                        'currency' => (string)($txn['transaction']['currency'] ?? ($txn['order']['currency'] ?? '')),
                        'timeOfRecord' => (string)($txn['timeOfRecord'] ?? ''),
                        'timeOfLastUpdate' => (string)($txn['timeOfLastUpdate'] ?? ''),

                        // Attempt order summary (never null)
                        'order' => [
                            'status' => (string)($txn['order']['status'] ?? ''),
                            'totalAuthorizedAmount' => (float)($txn['order']['totalAuthorizedAmount'] ?? 0),
                            'totalCapturedAmount' => (float)($txn['order']['totalCapturedAmount'] ?? 0),
                            'totalRefundedAmount' => (float)($txn['order']['totalRefundedAmount'] ?? 0),
                        ],

                        // Transaction ids (never null)
                        'mpgsTransactionId' => (string)($txn['transaction']['id'] ?? ''),
                        'receipt' => (string)($txn['transaction']['receipt'] ?? ''),
                        'acquirerTransactionId' => (string)($txn['transaction']['acquirer']['transactionId'] ?? ''),
                        'acquirerId' => (string)($txn['transaction']['acquirer']['id'] ?? ''),
                        'stan' => (string)($txn['transaction']['stan'] ?? ''),
                        'authorizationCode' => (string)($txn['transaction']['authorizationCode'] ?? ''),

                        // Card snapshot (never null)
                        'card' => [
                            'brand' => (string)($txn['sourceOfFunds']['provided']['card']['brand'] ?? ($data['sourceOfFunds']['provided']['card']['brand'] ?? '')),
                            'scheme' => (string)($txn['sourceOfFunds']['provided']['card']['scheme'] ?? ($data['sourceOfFunds']['provided']['card']['scheme'] ?? '')),
                            'fundingMethod' => (string)($txn['sourceOfFunds']['provided']['card']['fundingMethod'] ?? ($data['sourceOfFunds']['provided']['card']['fundingMethod'] ?? '')),
                            'number' => (string)($txn['sourceOfFunds']['provided']['card']['number'] ?? ($data['sourceOfFunds']['provided']['card']['number'] ?? '')),
                            'nameOnCard' => (string)($txn['sourceOfFunds']['provided']['card']['nameOnCard'] ?? ''),
                        ],
                    ];
                }
            }

            $updateData = [
                'paymentStatus' => $finalPaymentStatus,
                'gatewayResult' => $gatewayResult,
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
                'captured' => $isCaptured,
                'capturedAmount' => $captureAmountForDb,

                // Flag that cron/return processing happened
                'cron' => true,

                // Keep payment attempts history (PAYMENT txns only)
                'attempts' => $attempts,
            ];

            if (!$uuid) {
                error_log("UUID not set in session! Cannot update MongoDB.");
            } else {
                // Update the record for this UUID (full update including attempts)
                $collection->updateOne(
                    ['uuid' => $uuid],
                    ['$set' => $updateData]
                );

                // Update all records that share this orderId too (cron flag only)
                if (!empty($orderId) && $orderId !== 'no-order-id') {
                    $collection->updateMany(
                        ['orderId' => $orderId, 'uuid' => ['$ne' => $uuid]],
                        ['$set' => ['cron' => true, 'updatedAt' => $lastUpdated]]
                    );
                }

                error_log("set: " . json_encode($updateData));
            }
        } catch (Exception $e) {
            error_log("MongoDB Update Error: " . $e->getMessage());
        }

        $subject = "Payment Status Update for - OID:$orderId ";
        if ($mailStatus == 'payment error') {
            $body = '
            <div style="font-family: Arial, sans-serif; color: #721c24; background-color: #f8d7da; padding: 20px; border-radius: 5px; border: 1px solid #f5c6cb;">
                <h2 style="color: #721c24; margin-top: 0;">❌ Payment Unsuccessful </h2>
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
                <h2 style="color:#155724; margin-top: 0;">✅ Payment Successful <img src=https://d1yjjnpx0p53s8.cloudfront.net/styles/logo-original-577x577/s3/052018/untitled-1_140.png?FIodUHBMSE1tE0IMRJ7U4E9kw9w3BiZg&itok=GqPUzdYf alt="Bank Icon" style="width: 30px; height: 30px; vertical-align: middle;"></h2>
                <div style="background-color: white; padding: 15px; border-radius: 4px;">
                    <h3 style="margin: 0 0 10px 0;">Order Details</h3>
                    <table>
                        <tr><td style="padding: 5px 10px 5px 0;"><strong>Order ID:</strong></td><td>' . htmlspecialchars($orderId) . '</td></tr>
                        <tr><td style="padding: 5px 10px 5px 0;"><strong>Transaction ID:</strong></td><td>' . htmlspecialchars($transactionId) . '</td></tr>
                         <tr><td style="padding: 5px 10px 5px 0;"><strong> Card Number:</strong></td><td>' . htmlspecialchars($cardNumber) . '</td></tr>
                          <tr><td style="padding: 5px 10px 5px 0;"><strong>  Card Holder Name:</strong></td><td>' . htmlspecialchars($nameOnCard) . '</td></tr>
                        <tr><td style="padding: 5px 10px 5px 0;"><strong>Amount:</strong></td><td>' . htmlspecialchars($amount) . ' ' . htmlspecialchars($currency) . '</td></tr>
                    </table>
                    <p style="margin: 15px 0 0 0; color: #155724;">Thank you for your payment with Malkey Rent A Car.</p>
                </div>
            </div>';
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
                $mail->addCC($cc);
            }
               foreach (BCC_LIST as $bcc) {
             $mail->addBCC($bcc);
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
        $uiStatusText = 'ERROR';
    }
} else {
    $paymentStatus = "Error retrieving order details (HTTP Code: $httpCode)";
    $uiStatusText = 'ERROR';
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
        #payment-status.success { color: #155724; }
        #payment-status.error { color: #721c24; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen flex items-center justify-center p-4">
    <div id="main-container" class="bg-white rounded-2xl shadow-2xl transition-all duration-300 hover:shadow-xl w-full max-w-lg overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 text-center">
            <img src="https://d8asu6slkrh4m.cloudfront.net/2013/04/malkey-logo.png" alt="Logo" class="w-40 h-19 mx-auto mb-2 filter brightness-0 invert">
            <h1 class="text-2xl font-bold text-blue-100">Secure Payment</h1>
            <p class="text-blue-100 text-sm">Protected by Commercial Bank</p>
        </div>
        <div id="payment-status" class="mt-6 text-center text-lg font-semibold <?php echo ($isPaidSuccess ? 'success' : 'error'); ?>">
            Payment Status: <?php echo htmlspecialchars($uiStatusText); ?><br>
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
                <img src="assets/sponser.png" alt="bank logo" class="h-10">
            </div>
        </div>
    </div>
</body>
</html>

