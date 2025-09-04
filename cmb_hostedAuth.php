<?php
require_once 'config/config.php';
//require_once 'config/config.sample.php';
require 'vendor/autoload.php';

// Initialize error message
$errorMessage = null;

// Get input values
$amount = isset($_GET['amount']) ? $_GET['amount'] : '';
$currency = isset($_GET['currency']) ? $_GET['currency'] : 'LKR';
$description = isset($_GET['description']) ? $_GET['description'] : 'no description';
$orderId = isset($_GET['orderId']) ? $_GET['orderId'] : '10601';

if (empty($amount)) {
    $errorMessage = "Error: Amount is required.";
} elseif (!is_numeric($amount) || $amount <= 0) {
    $errorMessage = "Error: Amount must be a valid number greater than 0.";
}

if (!$errorMessage) {
    // --- Store session data ---
    session_start();
    $_SESSION['orderId'] = $orderId;
    $_SESSION['currency'] = $currency;

    // --- Set credentials based on currency ---
    if ($currency == 'LKR') {
        $merchantId = MERCHANT_ID_LKR;
        $apiUserName = API_USERNAME_LKR;
        $apiPassWord = API_PASSWORD_LKR;
    } else {
        $merchantId = MERCHANT_ID_USD;
        $apiUserName = API_USERNAME_USD;
        $apiPassWord = API_PASSWORD_USD;
    }

    // --- Prepare request ---
    $url = "https://cbcmpgs.gateway.mastercard.com/api/nvp/version/61";

    $data = http_build_query([
        'apiOperation' => 'CREATE_CHECKOUT_SESSION',
        'apiUsername' => $apiUserName,
        'apiPassword' => $apiPassWord,
        'merchant' => $merchantId,
        'order.id' => $orderId,
        'order.amount' => $amount,
        'order.currency' => $currency,
        'order.description' => $description,
        'interaction.operation' => 'PURCHASE',
        'interaction.returnUrl' => REDIRECT_URL,
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

    // --- Send request ---
    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);

    if ($response === false) {
        $error_msg = curl_error($ch);
        error_log($error_msg);
        curl_close($ch);
        $errorMessage = "Error: Failed to connect to payment gateway. Please try again.";
    } else {
        curl_close($ch);
        parse_str($response, $result);
        if (!isset($result['session_id'])) {
            $errorMessage = "Error: Failed to create session. Please try again.";
        } else {
            $sessionId = $result['session_id'];
        }
    }
}
