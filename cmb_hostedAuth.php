<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "cmb_hostedAuth.php";
require "vendor/autoload.php";


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
    $tenent = TENANT;


    $_SESSION['uuid'] = $uuid;
    $_SESSION['orderId'] = $orderId;
    $_SESSION['currency'] = $currency;

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

        try {
            $plutosDb = "DT-Plutos";
            $tenantCollection = $tenent;

            $clientPlutos = new Client($database_url);
            $tenantCol = $clientPlutos->$plutosDb->$tenantCollection;

            $liveStatus = defined('APP_LIVE') ? APP_LIVE : false;

            $tenantCol->updateOne(
                ['currency' => $currency, 'live' => $liveStatus], 
                ['$set' => [
                    'merchantId' => $merchantId,
                    'apiUserName' => $apiUserName,
                    'apiPassWord' => $apiPassWord,
                    'updatedAt' => new UTCDateTime()
                ],
                '$setOnInsert' => [
                    'createdAt' => new UTCDateTime()
                ]],
                ['upsert' => true]
            );

        } catch (Exception $e) {
            error_log("MongoDB Tenant Error: " . $e->getMessage());
        }
        $url = "https://cbcmpgs.gateway.mastercard.com/api/rest/version/100/merchant/{$merchantId}/session";
        $data = json_encode([
            "apiOperation" => "INITIATE_CHECKOUT",
            "interaction" => [
                "merchant" => [
                    "name" => NAME
                ],
                "operation" => "PURCHASE",
                "displayControl" => [
                    "billingAddress" => "HIDE",
                    "customerEmail" => "HIDE",
                    "shipping" => "HIDE"
                ],
                "returnUrl" => REDIRECT_URL,
                "cancelUrl" => REDIRECT_URL,
                "timeoutUrl" => REDIRECT_URL
            ],
            "order" => [
                "id" => $orderId,
                "currency" => $currency,
                "description" => $description,
                "amount" => number_format((float)$amount, 2, '.', '')
            ]
        ]);

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Cache-Control: no-cache"
            ],
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "merchant.{$merchantId}:{$apiPassWord}",
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
            $result = json_decode($response, true);

            if (!isset($result['session']['id'])) {
                $errorMessage = "Error: Failed to create session. Please try again.";
            } else {
                $sessionId = $result['session']['id'];

                try {
                    $client = new Client($database_url);
                    $collection = $client->$database->$collection;

                    $insertResult = $collection->insertOne([
                        'orderId' => $orderId,
                        'uuid' => $uuid,
                        'cron' => false,
                        'amount' => (float) $amount,
                        'currency' => $currency,
                        'description' => $description,
                        'merchantId' => $merchantId,
                        'sessionId' => $sessionId,
                        'bank' => "Commercial Bank",
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
            header("Location: " . BASE_PATH . "?txnId={$txnId}");
            exit;
        }
    }
}
