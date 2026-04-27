<?php
// session already started in bootstrap.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;

// ── Get email from cookie ─────────────────────────────────────────────────────
if (isset($_COOKIE['userEmail'])) {
    $email = filter_var($_COOKIE['userEmail'], FILTER_SANITIZE_EMAIL);
    $_SESSION['email'] = $email;
} else {
    $email = $_SESSION['email'] ?? 'example@example.com';
}

$uuid    = $_SESSION['uuid']    ?? null;
$orderId = $_SESSION['orderId'] ?? 'no-order-id';

// ── Call 2: PAYMENT_COMPLETE (PDF §6.9) ───────────────────────────────────────
// Paycorp redirects back to returnUrl via GET ?ReqID=
$reqid = $_GET['ReqID'] ?? null;

if (!$reqid) {
    error_log("response.php: No ReqID in GET params.");
}

$paymentStatus = 'UNKNOWN';
$emailMessage  = '';
$txnData       = [];

// Paycorp PAYMENT_COMPLETE fields: operation + clientId + reqid
$payload   = json_encode([
    'operation' => 'PAYMENT_COMPLETE',
    'clientId'  => (int) PAYCENTER_CLIENT_ID,
    'reqid'     => (string) $reqid,
]);
$signature = hash_hmac('sha256', $payload, PAYCENTER_HMAC_SECRET);

error_log("PAYMENT_COMPLETE payload: " . $payload);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => PAYCENTER_API_URL,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'authtoken: ' . PAYCENTER_AUTH_TOKEN,
        'signature: '  . $signature,
    ],
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

error_log("PAYMENT_COMPLETE response [HTTP $httpCode]: " . $response);

// ── Parse response (PDF §6.10) ────────────────────────────────────────────────
$responseCode = null;
$responseText = null;
$txnReference = null;
$holderName   = null;
$cardNumber   = null;
$cardExpiry   = null;
$amount       = '0.00';
$currency     = $_SESSION['payments'][$_SESSION['txnId'] ?? '']['currency'] ?? 'LKR';

if ($response !== false) {
    $txnData = json_decode($response, true) ?? [];

    $responseCode = $txnData['responseCode'] ?? null;   // "00" = success (PDF §7.1.3)
    $responseText = $txnData['responseText'] ?? null;
    $txnReference = (string)($txnData['txnReference'] ?? '');
    $holderName   = $txnData['creditCard']['holderName'] ?? 'N/A';
    $cardNumber   = $txnData['creditCard']['number']     ?? 'N/A';
    $cardExpiry   = $txnData['creditCard']['expiry']     ?? 'N/A';
    $amountCents  = $txnData['amount']['paymentAmount']  ?? 0;
    $currency     = $txnData['amount']['currency']       ?? $currency;
    $amount       = number_format($amountCents / 100, 2, '.', ',');

    // responseCode "00" = SUCCESS per PDF §7.1.3
    $isSuccess    = ($responseCode === '00' || strtoupper($responseText ?? '') === 'SUCCESS');
    $paymentStatus = $isSuccess ? 'SUCCESS' : 'ERROR';
    $mailStatus    = $isSuccess ? 'success' : 'payment error';

    // ── Update MongoDB ────────────────────────────────────────────────────────
    try {
        $client      = new Client(DATABASE_URL);
        $mongoCol    = $client->selectDatabase(DB)->selectCollection(COLLECTION);

        $updateData = [
            'paymentStatus'  => $paymentStatus,
            'responseCode'   => $responseCode,
            'responseText'   => $responseText,
            'txnReference'   => $txnReference,
            'holderName'     => $holderName,
            'cardNumber'     => $cardNumber,
            'cardExpiry'     => $cardExpiry,
            'amount'         => $amount,
            'currency'       => $currency,
            'email'          => $email,
            'gatewayResponse'=> $txnData,
            'updatedAt'      => new UTCDateTime(),
        ];

        $mongoCol->updateOne(
            ['reqid' => (string)$reqid],
            ['$set'  => $updateData]
        );

        error_log("MongoDB updated for reqid: $reqid");
    } catch (Exception $e) {
        error_log("MongoDB Error: " . $e->getMessage());
    }

    // ── Send email ────────────────────────────────────────────────────────────
    $subject = "Payment Status Update - OID: $orderId";

    if ($mailStatus === 'success') {
        $body = '
        <div style="font-family:Arial,sans-serif;color:#155724;background-color:#d4edda;padding:20px;border-radius:5px;border:1px solid #c3e6cb;">
            <h2 style="margin-top:0;">✅ Payment Successful</h2>
            <div style="background-color:white;padding:15px;border-radius:4px;">
                <h3 style="margin:0 0 10px 0;">Order Details</h3>
                <table>
                    <tr><td style="padding:5px 10px 5px 0;"><strong>Order ID:</strong></td><td>' . htmlspecialchars($orderId) . '</td></tr>
                    <tr><td style="padding:5px 10px 5px 0;"><strong>Paycorp Txn Ref:</strong></td><td>' . htmlspecialchars($txnReference) . '</td></tr>
                    <tr><td style="padding:5px 10px 5px 0;"><strong>Card Number:</strong></td><td>' . htmlspecialchars($cardNumber) . '</td></tr>
                    <tr><td style="padding:5px 10px 5px 0;"><strong>Card Holder:</strong></td><td>' . htmlspecialchars($holderName) . '</td></tr>
                    <tr><td style="padding:5px 10px 5px 0;"><strong>Amount:</strong></td><td>' . htmlspecialchars($currency) . ' ' . htmlspecialchars($amount) . '</td></tr>
                </table>
                <p style="margin:15px 0 0 0;">Thank you for your donation to HelpAge Sri Lanka.</p>
            </div>
        </div>';
    } else {
        $body = '
        <div style="font-family:Arial,sans-serif;color:#721c24;background-color:#f8d7da;padding:20px;border-radius:5px;border:1px solid #f5c6cb;">
            <h2 style="margin-top:0;">❌ Payment Failed</h2>
            <div style="background-color:white;padding:15px;border-radius:4px;">
                <h3 style="margin:0 0 10px 0;">Order Details</h3>
                <table>
                    <tr><td style="padding:5px 10px 5px 0;"><strong>Order ID:</strong></td><td>' . htmlspecialchars($orderId) . '</td></tr>
                    <tr><td style="padding:5px 10px 5px 0;"><strong>Response Code:</strong></td><td>' . htmlspecialchars((string)$responseCode) . '</td></tr>
                    <tr><td style="padding:5px 10px 5px 0;"><strong>Response Text:</strong></td><td>' . htmlspecialchars((string)$responseText) . '</td></tr>
                    <tr><td style="padding:5px 10px 5px 0;"><strong>Amount:</strong></td><td>' . htmlspecialchars($currency) . ' ' . htmlspecialchars($amount) . '</td></tr>
                </table>
            </div>
        </div>';
    }

    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;
        $mail->setFrom(MAIL_ADDRESS, MAIL_NAME);
        $mail->addAddress($email);
        foreach (CC_LIST as $cc) { $mail->addCC($cc); }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        $emailMessage = "Email sent successfully.";
    } catch (Exception $e) {
        $emailMessage = "Email could not be sent: {$mail->ErrorInfo}";
        error_log($emailMessage);
    }
} else {
    $paymentStatus = "Error connecting to payment gateway.";
    error_log("PAYMENT_COMPLETE cURL failed.");
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
            <!-- <img src="https://www.helpagesl.org/wp-content/uploads/2016/05/logo.png" alt="Logo" class="w-20 h-10 mx-auto mb-2 shadow-xl"> -->
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

        <!-- <button 
            onclick="window.location.href='pdf.php'" 
            class="text-red-400 underline hover:text-red-800 transition-all duration-300 transform hover:scale-[1.02] mt-4 w-1/2 mx-auto">
            Download Receipt 
        </button> -->
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