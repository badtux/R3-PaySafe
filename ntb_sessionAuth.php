<?php
require_once 'config/config.php';
require 'vendor/autoload.php';

// Start the session
session_start();

// Get orderId from GET parameter or set a default
$amount = isset($_GET['amount']) ? $_GET['amount'] : "1.00";
$currency = isset($_GET['currency']) ? $_GET['currency'] : "USD";
$description = isset($_GET['description']) ? $_GET['description'] : "no description";
$orderId = isset($_GET['orderId']) ? $_GET['orderId'] : "no order id";

// Store orderId in session
$_SESSION['orderId'] = $orderId;

$url = "https://nationstrustbankplc.gateway.mastercard.com/api/rest/version/81/merchant/" . MERCHANT_ID . "/session";

$data = [
    "apiOperation" => "INITIATE_CHECKOUT",
    "checkoutMode" => "WEBSITE",
    "interaction" => [
        "operation" => "AUTHORIZE",
        "merchant" => [
            "name" => NAME,
            "logo" => LOGO,
            "url" => "https://www.malkey.lk",
            "phone" => "+94-112365365",
            "email" => "info@malkey.lk"
        ],
        "returnUrl" => "http://cmbgateway.loc/response.php",


        "locale" => "en_US",
        "style" => [
            "theme" => "default"
        ]
    ],
    "order" => [
        "currency" => $currency,
        "amount" => $amount,
        "id" => $orderId,
        "description" => $description
    ]
];

$options = [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        "Content-Type: text/plain",
        "Authorization: Basic " . base64_encode(API_USERNAME . ":" . API_PASSWORD)
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FAILONERROR => true
];

$ch = curl_init();
curl_setopt_array($ch, $options);
$response = curl_exec($ch);

if ($response === false) {
    $error_msg = curl_error($ch);
    $error_msg = curl_strerror(curl_errno($ch));
    error_log($error_msg);
    curl_close($ch);
    die("cURL error: " . $error_msg);
}
curl_close($ch);

$result = json_decode($response, true);
if (isset($result['session']['id'])) {
    $sessionId = $result['session']['id'];
} else {
    die("Failed to create session: " . json_encode($result));
}
