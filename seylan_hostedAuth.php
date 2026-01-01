<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("Session ID: " . session_id());
error_log("Session data at start: " . print_r($_SESSION, true)); 

require_once('config/config.php');
require "vendor/autoload.php";

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use Ramsey\Uuid\Uuid;

$errorMessage = null;
$txnId = isset($_GET['txnId']) ? $_GET['txnId'] : null;
$uuid = Uuid::uuid4()->toString();

error_log("Generated UUID: $uuid"); 

$database_url = defined('DATABASE_URL') ? DATABASE_URL : '';
$collection_name = defined('COLLECTION') ? COLLECTION : '';
$database_name = defined('DB') ? DB : '';
$merchantId = defined('MERCHANT_ID') ? MERCHANT_ID : '';
$apiUserName = defined('API_USERNAME') ? API_USERNAME : '';
$apiPassWord = defined('API_PASSWORD') ? API_PASSWORD : '';
$basePath = defined('BASE_PATH') ? BASE_PATH : '';

error_log("Config values - merchantId: $merchantId, database_url: $database_url, collection: $collection_name, db: $database_name, basePath: $basePath"); 

if (!$txnId) {

    error_log("No txnId provided - starting new payment flow"); 

     
    $amount = isset($_GET['amount']) ? $_GET['amount'] : '';
    $currency = isset($_GET['currency']) ? $_GET['currency'] : 'LKR';
    $description = isset($_GET['description']) ? $_GET['description'] : 'No description provided.';
    $orderId = isset($_GET['orderId']) ? $_GET['orderId'] : '';

    error_log("Input params - amount: $amount, currency: $currency, orderId: $orderId, description: $description"); 

    $_SESSION['uuid'] = $uuid;
    $_SESSION['orderId'] = $orderId;
    $_SESSION['currency'] = $currency;

    error_log("UUID stored in session: " . ($_SESSION['uuid'] ?? 'not set'));
    error_log("Current session after setting values: " . print_r($_SESSION, true)); 

    if ($amount === false || $amount <= 0) {
        $errorMessage = "Error: Amount is required and must be a valid number greater than 0.";
        error_log("Validation failed - invalid amount: " . var_export($amount, true));
    } elseif (empty($orderId)) {
        $errorMessage = "Error: Order ID is required and cannot be empty.";
        error_log("Validation failed - empty orderId");
    } elseif (empty($merchantId) || empty($apiPassWord) || empty($database_url) || empty($collection_name) || empty($database_name)) {
        $errorMessage = "Error: Configuration values are missing.";
        error_log("Config missing - merchantId: $merchantId, apiPassWord: " . (!empty($apiPassWord) ? 'SET' : 'EMPTY') . ", database_url: $database_url");
    } else {
        error_log("All validations passed - proceeding to create session"); 

        $txnId = bin2hex(random_bytes(8));
        error_log("Generated txnId: $txnId"); 

        $_SESSION['payments'][$txnId] = [
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'orderId' => $orderId,
            'uuid' => $uuid
        ];
        error_log("Payment data stored in session for txnId $txnId: " . print_r($_SESSION['payments'][$txnId], true));

        $authString = "merchant.$merchantId:$apiPassWord";
        $authHeader = "Authorization: Basic " . base64_encode($authString);
        $url = rtrim(API_URL, '/') . '/' . rawurlencode($merchantId) . '/session';
          error_log("MPGS SESSION URL => " . $url);       
        $data = [
            "apiOperation" => "INITIATE_CHECKOUT",
            "interaction" => [
                "operation" => "AUTHORIZE",
                "merchant" => [
                    "name" => NAME,
                    "logo" => LOGO,
                    "url" => "https://www.helpagesl.org/",
                    "phone" => "+94 11 7418977",
                    "email" => "helpage@sltnet.lk"
                ],
                "returnUrl" => REDIRECT_URL,
            ],
            "order" => [
                "currency" => $currency,
                "amount" => $amount,
                "id" => $orderId,
                "description" => $description
            ]
        ];

        $jsonData = json_encode($data);
        error_log("Request Data to Mastercard Gateway: " . $jsonData); 

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Cache-Control: no-cache",
                $authHeader
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        error_log("Initiating cURL request to: $url"); 

        $response = curl_exec($ch);

        if ($response === false) {
            $error_msg = curl_error($ch);
            $curl_info = curl_getinfo($ch);
            error_log("cURL Error: $error_msg | Info: " . print_r($curl_info, true));
            curl_close($ch);
            $errorMessage = "Error: Failed to connect to payment gateway. Please try again.";
        } else {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            error_log("cURL Success - HTTP Code: $http_code | Raw Response: $response"); 
            curl_close($ch);

            $result = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($result['session']['id'])) {
                $errorMessage = "Error: Failed to create session. Invalid response from gateway.";
                error_log("JSON Decode Error: " . json_last_error_msg() . " | Full Response: $response");
            } else {
                $sessionId = $result['session']['id'];
                error_log("Session ID received from gateway: $sessionId"); 
                try {
                    $client = new Client($database_url);
                    error_log("MongoDB Client connected to: $database_url");

                    $collection = $client->selectDatabase($database_name)->selectCollection($collection_name);

                    $insertResult = $collection->insertOne([
                        'orderId' => $orderId,
                        'uuid' => $uuid,
                        'amount' => (float) $amount,
                        'currency' => $currency,
                        'description' => $description,
                        'merchantId' => $merchantId,
                        'sessionId' => $sessionId,
                        'bank'   => "Seylan Bank",
                        'createdAt' => new UTCDateTime()
                    ]);

                    error_log("MongoDB Insert Result - Inserted Count: " . $insertResult->getInsertedCount() . " | Inserted ID: " . $insertResult->getInsertedId());

                    if ($insertResult->getInsertedCount() <= 0) {
                        $errorMessage = "Error: Failed to store transaction in MongoDB.";
                    } else {
                        $_SESSION['payments'][$txnId]['sessionId'] = $sessionId;
                        error_log("SessionId stored in session for txnId $txnId");
                    }
                } catch (Exception $e) {
                    $errorMessage = "MongoDB Error: " . $e->getMessage();
                    error_log("MongoDB Exception: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
                }
            }
        }


        // echo 'ASASA';
        // exit;

        if (!$errorMessage) {
            $redirectUrl = "$basePath?txnId=$txnId";
            error_log("All good! Redirecting to: $redirectUrl"); 
            header("Location: $redirectUrl");
            exit;
        } else {
            $encodedError = urlencode($errorMessage);
            $errorRedirect = "$basePath?errorMessage=$encodedError";
            error_log("Error occurred - Redirecting with error: $errorRedirect");
            header("Location: $errorRedirect");
            exit;
        }
    }
} else {
    error_log("txnId provided: $txnId - likely returning from gateway or loading existing session"); 
}