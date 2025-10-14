<!-- <?php
require_once 'config/config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use Ramsey\Uuid\Uuid;


session_start();

// --- Capture Email from POST ---

if (!isset($_SESSION['uuid'])) {
    $_SESSION['uuid'] = Uuid::uuid4()->toString();
}
$uuid = $_SESSION['uuid'];


if (isset($_POST['email'])) {
    $email = $_POST['email'];
    $_SESSION['email'] = $email;
} else {
    $email = $_SESSION['email'] ?? 'example@example.com';
}

// --- Session Data ---
$orderId = $_SESSION['orderId'] ?? 'no-order-id';
$currency = $_SESSION['currency'] ?? 'USD';

// --- Credentials based on currency ---
if ($currency == 'LKR') {
    $merchantId = MERCHANT_ID_LKR;
    $apiPassword = API_PASSWORD_LKR;
} else {
    $merchantId = MERCHANT_ID_USD;
    $apiPassword = API_PASSWORD_USD;
}

$gatewayUrl = "https://nationstrustbankplc.gateway.mastercard.com/api/rest/version/81/merchant/$merchantId/order/$orderId";

// --- Fetch Payment Status ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $gatewayUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, "merchant.$merchantId:$apiPassword");
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// --- Initialize vars ---
$paymentStatus = "UNKNOWN";
$emailMessage = "";
$emailSent = false;
$transactionId = "not-set";
$amount = "0.00";

// --- Process Response ---
if ($httpCode == 200 && $response) {
    $data = json_decode($response, true);

    if (!empty($data)) {
        $paymentStatus = htmlspecialchars($data['result'] ?? 'N/A');
        $transactionId = $data['3DSecure']['xid'] ?? 'not-set';
        $orderId = $data['id'] ?? $orderId;
        $amount = isset($data['amount']) ? number_format((float)$data['amount'], 2, '.', '') : '0.00';
        $currency = htmlspecialchars($data['currency'] ?? $currency);

        // --- Email Body ---
        $subject = "Payment Status Update";
        $body = "<h2>Payment Status: " . htmlspecialchars($paymentStatus) . "</h2>
                 <p><strong>Order ID:</strong> " . htmlspecialchars($orderId) . "</p>
                 <p><strong>Transaction ID:</strong> " . htmlspecialchars($transactionId) . "</p>
                 <p><strong>Amount:</strong> " . htmlspecialchars($amount) . " " . htmlspecialchars($currency) . "</p>";

        // --- Send Email ---
        $mail = new PHPMailer(true);
        try {
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
            $emailMessage = "✅ Email sent successfully.";
            $emailSent = true;
        } catch (Exception $e) {
            $emailMessage = "❌ Email could not be sent. Error: {$mail->ErrorInfo}";
            $emailSent = false;
        }

        // --- Save to MongoDB ---
        try {
            $client = new Client("mongodb://localhost:27017"); // adjust if container IP
            $collection = $client->paymentdb->notifications;

            $collection->insertOne([
                'email' => $email,
                'uuid'        => $uuid,
                'emailSent' => $emailSent,
                'orderId' => $orderId,
                'paymentStatus' => $paymentStatus,
                'transactionId' => $transactionId,
                'amount' => (float) $amount,
                'currency' => $currency,
                'createdAt' =>  new UTCDateTime()
            ]);
        } catch (Exception $e) {
            error_log("MongoDB Error: " . $e->getMessage());
        }
    } else {
        $paymentStatus = "Unable to decode response";
    }
} else {
    $paymentStatus = "Error retrieving order details (HTTP $httpCode)";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white shadow-lg rounded-lg p-6 max-w-lg w-full text-center">
        <h1 class="text-2xl font-bold mb-4">Payment Status</h1>
        <p class="text-lg font-semibold">
            Status: <span class="<?php echo ($paymentStatus === 'SUCCESS' ? 'text-green-600' : 'text-red-600'); ?>">
                <?php echo $paymentStatus; ?>
            </span>
        </p>
        <p><?php echo $emailMessage; ?></p>
        <div class="mt-4 text-left">
            <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
            <p><strong>Order ID:</strong> <?php echo htmlspecialchars($orderId); ?></p>
            <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($transactionId); ?></p>
            <p><strong>Amount:</strong> <?php echo htmlspecialchars($amount . " " . $currency); ?></p>
        </div>
        <button onclick="window.location.href='https://www.malkey.lk/'" 
                class="mt-6 bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">
            Return to Merchant
        </button>
    </div>
</body>
</html>


//session_start();
require_once "cmb_hostedAuth.php";
require_once "config/config.sample.php";
require "vendor/autoload.php";

// $amount = isset($_GET['amount']) ? $_GET['amount'] : null;
// $currency = isset($_GET['currency']) ? $_GET['currency'] : "LKR";
// $description = isset($_GET['description']) ? $_GET['description'] : "No description available.";
// $orderId = isset($_GET['orderId']) ? $_GET['orderId'] : "No order ID available.";
// $isValidAmount = !empty($amount) && is_numeric($amount) && $amount > 0;

if (!isset($_GET['txnId'])) {
    // First time: user came with all parameters
    $txnId = bin2hex(random_bytes(8));

    $_SESSION['payments'][$txnId] = [
        'amount'      => $_GET['amount'] ?? null,
        'currency'    => $_GET['currency'] ?? 'LKR',
        'description' => $_GET['description'] ?? 'No description available.',
        'orderId'     => $_GET['orderId'] ?? 'No order ID available.',
    ];

    // Redirect to clean URL
    header("Location: paysafe.php?txnId={$txnId}");
    exit;
}

// Second time: only txnId is in URL
$txnId = $_GET['txnId'];
if (!isset($_SESSION['payments'][$txnId])) {
    die("Invalid transaction ID");
}

$payment     = $_SESSION['payments'][$txnId];
$amount      = $payment['amount'];
$currency    = $payment['currency'];
$description = $payment['description'];
$orderId     = $payment['orderId'];
$isValidAmount = !empty($amount) && is_numeric($amount) && $amount > 0;
if (!$isValidAmount) {
    $errorMessage = "Error: Amount is required.";
}
elseif (!isset($sessionId)) {
    $errorMessage = "Error: Session could not be created. Please try again.";
} -->
