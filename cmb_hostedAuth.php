<?php
session_start();
require_once "cmb_hostedAuth.php";
//require_once "config/config.sample.php";
require "vendor/autoload.php";
require_once('config/config.php');


use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use Ramsey\Uuid\Uuid;

$errorMessage = null;
$txnId = isset($_GET['txnId']) ? $_GET['txnId'] : null;
$uuid = Uuid::uuid4()->toString();
if (!$txnId) {

    $amount = isset($_GET['amount']) ? $_GET['amount'] : '';
    $currency = isset($_GET['currency']) ? $_GET['currency'] : 'LKR';
    $description = isset($_GET['description']) ? $_GET['description'] : 'No description provided.';
    $orderId = isset($_GET['orderId']) ? $_GET['orderId'] : '';
    $database_url = DATABASE_URL;
    $collection = COLLECTION;
    $database = DB;

    $_SESSION['uuid'] = $uuid;
    $_SESSION['orderId'] = $orderId;
    $_SESSION['currency'] =$currency;
    $isValidAmount = !empty($amount) && is_numeric($amount) && $amount > 0;
    $isValidOrderId = !empty($orderId);

    if (!$isValidAmount) {
        $errorMessage = "Error: Amount is required and must be a valid number greater than 0.";
    } elseif (!$isValidOrderId) {
        $errorMessage = "Error: Order ID is required and cannot be empty.";
    } else {
        $txnId = bin2hex(random_bytes(8));

        $_SESSION['payments'][$txnId] = [
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'orderId' => $orderId,
            'uuid' => $uuid
        ];

        if ($currency == 'LKR') {
            $merchantId = MERCHANT_ID_LKR;
            $apiUserName = API_USERNAME_LKR;
            $apiPassWord = API_PASSWORD_LKR;
        } else {
            $merchantId = MERCHANT_ID_USD;
            $apiUserName = API_USERNAME_USD;
            $apiPassWord = API_PASSWORD_USD;
        }

        // Prepare request for checkout session
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

                try {
                    $client = new Client($database_url);
                    $collection = $client->$database->$collection;

                    $insertResult = $collection->insertOne([
                        'orderId' => $orderId,
                        'uuid' => $uuid,
                        'amount' => (float) $amount,
                        'currency' => $currency,
                        'description' => $description,
                        'merchantId' => $merchantId,
                        'sessionId' => $sessionId,
                        'createdAt' => new UTCDateTime()
                    ]);

                    if ($insertResult->getInsertedCount() <= 0) {
                        $errorMessage = "Error: Failed to store transaction in MongoDB.";
                    } else {
                        $_SESSION['payments'][$txnId]['sessionId'] = $sessionId;
                    }
                } catch (Exception $e) {
                    $errorMessage = "MongoDB Error: " . $e->getMessage();
                }
            }
        }
        // Redirect to clean URL if no errors
        if (!$errorMessage) {
            header("Location:paysafe?txnId={$txnId}");
            exit;
        }
    }
}


