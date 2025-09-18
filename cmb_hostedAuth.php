<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);

// Define constants for error types
define('ERROR_VALIDATION', 1000);
define('ERROR_NETWORK', 1001);
define('ERROR_DATABASE', 1002);
define('ERROR_GATEWAY', 1003);
define('ERROR_UNKNOWN', 1004);

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Exception\Exception as MongoException;
use Ramsey\Uuid\Uuid;


// Initialize error tracking
$errorMessage = null;
$errorType = null;
$errorDetails = [];

try {
    // Load required files with error handling
    $requiredFiles = [
        'cmb_hostedAuth.php',
        'vendor/autoload.php',
       'config/config.php'
       // 'config/config.sample.php'
    ];

    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            throw new Exception("Required file not found: {$file}", ERROR_UNKNOWN);
        }
        require_once $file;
    };


    $txnId = isset($_GET['txnId']) ? trim($_GET['txnId']) : null;
    $uuid = Uuid::uuid4()->toString();

    if (!$txnId) {
        // Validate and sanitize input parameters
        $amount = isset($_GET['amount']) ? trim($_GET['amount']) : '';
        $currency = isset($_GET['currency']) ? strtoupper(trim($_GET['currency'])) : 'LKR';
        $description = isset($_GET['description']) ? trim($_GET['description']) : 'No description provided.';
        $orderId = isset($_GET['orderId']) ? trim($_GET['orderId']) : '';

        // Validate inputs
        if (empty($amount)) {
            throw new Exception("Amount is required", ERROR_VALIDATION);
        }

        if (!is_numeric($amount) || $amount <= 0) {
            throw new Exception("Amount must be a valid number greater than 0", ERROR_VALIDATION);
        }

        if (empty($orderId)) {
            throw new Exception("Order ID is required", ERROR_VALIDATION);
        }

        // Validate currency
        $validCurrencies = ['LKR', 'USD'];
        if (!in_array($currency, $validCurrencies)) {
            throw new Exception("Invalid currency. Supported currencies: " . implode(', ', $validCurrencies), ERROR_VALIDATION);
        }

        // Store session data
        $_SESSION['uuid'] = $uuid;
        $_SESSION['orderId'] = $orderId;
        $_SESSION['currency'] = $currency;

        // Generate transaction ID
        $txnId = bin2hex(random_bytes(8));

        if (!isset($_SESSION['payments'])) {
            $_SESSION['payments'] = [];
        }

        $_SESSION['payments'][$txnId] = [
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'orderId' => $orderId,
            'uuid' => $uuid
        ];

        // Set merchant credentials based on currency
        if ($currency == 'LKR') {
            $merchantId = defined('MERCHANT_ID_LKR') ? MERCHANT_ID_LKR : '';
            $apiUserName = defined('API_USERNAME_LKR') ? API_USERNAME_LKR : '';
            $apiPassWord = defined('API_PASSWORD_LKR') ? API_PASSWORD_LKR : '';
        } else {
            $merchantId = defined('MERCHANT_ID_USD') ? MERCHANT_ID_USD : '';
            $apiUserName = defined('API_USERNAME_USD') ? API_USERNAME_USD : '';
            $apiPassWord = defined('API_PASSWORD_USD') ? API_PASSWORD_USD : '';
        }

        // Validate merchant credentials
        if (empty($merchantId) || empty($apiUserName) || empty($apiPassWord)) {
            throw new Exception("Payment gateway configuration error", ERROR_GATEWAY);
        }

        // Prepare API request
        $url = "https://cbcmpgs.gateway.mastercard.com/api/nvp/version/61";
        $postData = [
            'apiOperation' => 'CREATE_CHECKOUT_SESSION',
            'apiUsername' => $apiUserName,
            'apiPassword' => $apiPassWord,
            'merchant' => $merchantId,
            'order.id' => $orderId,
            'order.amount' => $amount,
            'order.currency' => $currency,
            'order.description' => $description,
            'interaction.operation' => 'PURCHASE',
            'interaction.returnUrl' => defined('REDIRECT_URL') ? REDIRECT_URL : '',
            'interaction.merchant.name' => defined('NAME') ? NAME : ''
        ];

        // Validate redirect URL
        if (empty($postData['interaction.returnUrl'])) {
            throw new Exception("Redirect URL not configured", ERROR_GATEWAY);
        }

        $data = http_build_query($postData);

        // Initialize cURL with error handling
        $ch = curl_init();
        if ($ch === false) {
            throw new Exception("Failed to initialize cURL", ERROR_NETWORK);
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/x-www-form-urlencoded",
                "Cache-Control: no-cache"
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ];

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);

        if ($response === false) {
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);

            $errorDetails = [
                'curl_error' => $curlError,
                'curl_errno' => $curlErrno,
                'url' => $url
            ];

            throw new Exception("Failed to connect to payment gateway: {$curlError}", ERROR_NETWORK);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Payment gateway returned HTTP error: {$httpCode}", ERROR_GATEWAY);
        }

        parse_str($response, $result);

        if (!isset($result['session_id']) || empty($result['session_id'])) {
            $errorDetails['gateway_response'] = $response;
            throw new Exception("Failed to create payment session", ERROR_GATEWAY);
        }

        $sessionId = $result['session_id'];

        try {
            if (!defined('DATABASE_URL') || !defined('DB') || !defined('COLLECTION')) {
                throw new Exception("Database configuration missing", ERROR_DATABASE);
            }

            $client = new Client(DATABASE_URL);
            $database = DB;
            $collectionName = COLLECTION;

            $collection = $client->$database->$collectionName;

            $insertResult = $collection->insertOne([
                'orderId' => $orderId,
                'uuid' => $uuid,
                'amount' => (float) $amount,
                'currency' => $currency,
                'description' => $description,
                'merchantId' => $merchantId,
                'sessionId' => $sessionId,
                'createdAt' => new UTCDateTime(),
                'status' => 'session_created'
            ]);

            if ($insertResult->getInsertedCount() <= 0) {
                throw new Exception("Failed to store transaction in database", ERROR_DATABASE);
            }

            $_SESSION['payments'][$txnId]['sessionId'] = $sessionId;


           header("Location: " . BASE_PATH . "?txnId=" . urlencode($txnId));
            exit;
        } catch (Exception $e) {
            $errorDetails['mongo_error'] = $e->getMessage();
            throw new Exception("Database error: " . $e->getMessage(), ERROR_DATABASE);
        }
    }
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $errorType = $e->getCode() ?: ERROR_UNKNOWN;

    error_log("Payment Error [{$errorType}]: {$errorMessage} " .
        (!empty($errorDetails) ? json_encode($errorDetails) : ''));
}

if ($errorMessage) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Set appropriate HTTP status code
    switch ($errorType) {
        case ERROR_VALIDATION:
            http_response_code(400);
            break;
        case ERROR_NETWORK:
        case ERROR_GATEWAY:
            http_response_code(502);
            break;
        case ERROR_DATABASE:
            http_response_code(500);
            break;
        default:
            http_response_code(500);
    }
    exit;
}
