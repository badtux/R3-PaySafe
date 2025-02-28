<?php
require_once 'config/config.php';
require 'vendor/autoload.php';

$amount = isset($_GET['amount']) ? $_GET['amount'] : "1.00";
$currency = isset($_GET['currency']) ? $_GET['currency'] : "LKR";
$description = isset($_GET['description']) ? $_GET['description'] : "TEST ORDER";
$orderId = isset($_GET['orderId']) ? $_GET['orderId'] : "10601";

$url = "https://cbcmpgs.gateway.mastercard.com/api/nvp/version/61";

$data = http_build_query([
    'apiOperation' => 'CREATE_CHECKOUT_SESSION',
    'apiUsername' => API_USERNAME,
    'apiPassword' => API_PASSWORD,
    'merchant' => MERCHANT_ID,
    'order.id' => $orderId,
    'order.amount' => $amount,
    'order.currency' => $currency,
    'order.description' => $description,
    'interaction.operation' => 'PURCHASE',
    'interaction.returnUrl' => 'http://cmbgateway.loc/response.php',
    'interaction.merchant.name' => NAME
]);

$options = [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/x-www-form-urlencoded",
        "Cache-Control: no-cache"
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FAILONERROR => true
];

$ch = curl_init();
curl_setopt_array($ch, $options);
$response = curl_exec($ch);

if ($response === false) {
    $error_msg = curl_error($ch);
    error_log($error_msg);
    curl_close($ch);
    die("cURL error: " . $error_msg);
}
curl_close($ch);

parse_str($response, $result);
if (isset($result['session_id'])) {
    $sessionId = $result['session_id'];
} else {
    die("Failed to create session: " . json_encode($result));
}

?>
