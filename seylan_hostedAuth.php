<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("Session ID: " . session_id());
require_once('config/config.php');
require_once "seylan_hostedAuth.php";
require "vendor/autoload.php";

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use Ramsey\Uuid\Uuid;

$errorMessage = null;
$txnId = filter_input(INPUT_GET, 'txnId', FILTER_SANITIZE_STRING);
$uuid = Uuid::uuid4()->toString();


$database_url = defined('DATABASE_URL') ? DATABASE_URL : '';
$collection_name = defined('COLLECTION') ? COLLECTION : '';
$database_name = defined('DB') ? DB : '';
$merchantId = defined('MERCHANT_ID') ? MERCHANT_ID : '';
$apiUserName = defined('API_USERNAME') ? API_USERNAME : '';
$apiPassWord = defined('API_PASSWORD') ? API_PASSWORD : '';
$basePath = defined('BASE_PATH') ? BASE_PATH : '';

if (!$txnId) {

    $amount = filter_input(INPUT_GET, 'amount', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0.01]]);
    $currency = filter_input(INPUT_GET, 'currency', FILTER_SANITIZE_STRING) ?? 'LKR';
    $description = filter_input(INPUT_GET, 'description', FILTER_SANITIZE_STRING) ?? 'No description provided.';
    $orderId = filter_input(INPUT_GET, 'orderId', FILTER_SANITIZE_STRING) ?? '';


    $_SESSION['uuid'] = $uuid;
    $_SESSION['orderId'] = $orderId;
    $_SESSION['currency'] = $currency;

    error_log("UUID in session: " . ($_SESSION['uuid'] ?? 'not set'));

    if ($amount === false || $amount <= 0) {
        $errorMessage = "Error: Amount is required and must be a valid number greater than 0.";
         error_log("amount: " . $amount);
    } elseif (empty($orderId)) {
        error_log("order id: " . $orderId);
        $errorMessage = "Error: Order ID is required and cannot be empty.";
    } elseif (empty($merchantId) || empty($apiPassWord) || empty($database_url) || empty($collection_name) || empty($database_name)) {
        $errorMessage = "Error: Configuration values are missing.";
        error_log("merchantId: " . $merchantId);
        error_log("apiPassWord: " . $apiPassWord);
        error_log("database_url: " . $database_url);
        error_log("collection_name: " . $collection_name);
        error_log("database_name: " . $database_name);
    } else {

        $txnId = bin2hex(random_bytes(8));


        $_SESSION['payments'][$txnId] = [
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'orderId' => $orderId,
            'uuid' => $uuid
        ];
        $authString = "merchant.$merchantId:$apiPassWord";
        $authHeader = "Authorization: Basic " . base64_encode($authString);
        $url = "https://test-seylan.mtf.gateway.mastercard.com/api/rest/version/67/merchant/$merchantId/session";

        $data = [
            "apiOperation" => "INITIATE_CHECKOUT",
                  "interaction" => [
                "operation" => "PURCHASE",
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
         error_log("Request Data: " . $jsonData);
         error_log("Database: $database_url, Collection: $collection_name, DB: $database_name");

 
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

        $response = curl_exec($ch);

        if ($response === false) {
            $error_msg = curl_error($ch);
            error_log("cURL Error: $error_msg");
            curl_close($ch);
            $errorMessage = "Error: Failed to connect to payment gateway. Please try again.";
        } else {
            curl_close($ch);
            $result = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($result['session']['id'])) {
                $errorMessage = "Error: Failed to create session. Invalid response from gateway.";
                error_log("API Response Error: " . json_last_error_msg() . " Response: $response");
            } else {
                $sessionId = $result['session']['id'];

                try {
                    $client = new Client($database_url);
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

                    if ($insertResult->getInsertedCount() <= 0) {
                        $errorMessage = "Error: Failed to store transaction in MongoDB.";
                    } else {
                        $_SESSION['payments'][$txnId]['sessionId'] = $sessionId;
                        error_log("sessionId: " . $sessionId);
                    }
                } catch (Exception $e) {
                    $errorMessage = "MongoDB Error: " . $e->getMessage();
                    error_log("MongoDB Error: " . $e->getMessage());
                }
            }
        }

        echo 'ASASA';
        exit;
        
        if (!$errorMessage) {
            header("Location: $basePath?txnId=$txnId");
            exit;
        } else {
            $encodedError = urlencode($errorMessage);
            header("Location: $basePath?errorMessage=$encodedError");
            exit;
        }
    }
}
