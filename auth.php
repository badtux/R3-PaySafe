<?php
// auth.php
session_start();

require_once 'config/config.php';
require_once 'MastercardGateway.php';

$responseData = [];

if (!isset($_SESSION['txn']['price'], $_SESSION['txn']['currency'], $_SESSION['txn']['reference'])) {
    echo json_encode(['status' => 'ERROR', 'message' => 'Session expired or invalid transaction']);
    exit;
}
$cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
$expMonth = str_pad($_POST['exp_month'] ?? '', 2, '0', STR_PAD_LEFT);
$expYear = '20' . ($_POST['exp_year'] ?? '');
$cvv = preg_replace('/\D/', '', $_POST['cvv'] ?? '');

if (
    !preg_match('/^\d{13,19}$/', $cardNumber) ||
    !preg_match('/^\d{3,4}$/', $cvv) ||
    !checkdate((int)$expMonth, 1, (int)$expYear)
) {
    echo json_encode(['status' => 'ERROR', 'message' => 'Invalid card details']);
    exit;
}

$transactionData = [
    'order' => [
        'amount' => (float)$_SESSION['txn']['price'] * 100,
        'currency' => $_SESSION['txn']['currency'],
        'id' => $_SESSION['txn']['reference']
    ],
    'sourceOfFunds' => [
        'type' => 'CARD',
        'provided' => [
            'card' => [
                'number' => $cardNumber,
                'expiry' => [
                    'month' => $expMonth,
                    'year' => $expYear
                ],
                'securityCode' => $cvv
            ]
        ]
    ]
];
echo json_encode($transactionData, JSON_PRETTY_PRINT);


try {
    $config = [
        'gatewayUrl' => 'https://nationstrustbankplc.gateway.mastercard.com/api/rest/version/100/information',
        'version' => '100',
        'merchantId' =>MERCHANT_ID,
        'apiPassword' =>API_PASSWORD
    ];
    
    $merchant = new Merchant($config);
    $connection = new Connection();
    $requestBody = $connection->ParseRequest($transactionData);
    $customUri = '/merchant/' . MERCHANT_ID . '/order/' . $_SESSION['txn']['reference'] . '/transaction';
    $response = $connection->SendTransaction($merchant, $requestBody, $customUri, 'POST');
    if (
        isset($response['status']) && $response['status'] === 200 &&
        isset($response['response']['status']) && $response['response']['status'] === 'SUCCESS'
    ) {
        $responseData = [
            'status' => 'APPROVED',
            'message' => 'Payment processed successfully',
            'headers' => apache_request_headers(),
            'response' => $response
        ];
    } else {
        $error = $response['response']['error']['explanation'] ?? 'Payment processing failed';
        $responseData = [
            'status' => 'DECLINED',
            'message' => $error,
            'headers' => apache_request_headers(),
            'response' => $response
        ];
    }
} catch (Exception $e) {
    $responseData = [

        'status' => 'ERROR',
        'message' => 'Payment error: ' . $e->getMessage(),
        'headers' => apache_request_headers()
    ];
}

unset($_SESSION['txn']);
session_regenerate_id(true);

echo json_encode($responseData);
exit;
