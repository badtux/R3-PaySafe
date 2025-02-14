<?php



$name = isset($_POST['name']) ? $_POST['name'] : 'Customer';
$reference = isset($_POST['reference']) ? $_POST['reference'] : 'No reference';
$email = isset($_POST['email']) ? $_POST['email'] : '';
$price = isset($_POST['price']) ? floatval($_POST['price']) : 0.0;
$currency_p = isset($_POST['currency']) ? strtoupper($_POST['currency']) : 'LKR';
$cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
$expMonth = str_pad($_POST['exp_month'] ?? '', 2, '0', STR_PAD_LEFT);
$expYear = '20' . ($_POST['exp_year'] ?? '');
$cvv = preg_replace('/\D/', '', $_POST['cvv'] ?? '');
$amount_p = $price > 0 ? intval($price * 100) : 0;




class DirectPayment {
    private $merchantId;
    private $apiUsername;
    private $apiPassword;
    private $gatewayUrl;
    private $version;

    public function __construct($merchantId, $apiUsername, $apiPassword, $gatewayUrl, $version = "81") {
        $this->merchantId = $merchantId;
        $this->apiUsername = $apiUsername;
        $this->apiPassword = $apiPassword;
        $this->gatewayUrl = $gatewayUrl;
        $this->version = $version;
    }

    public function checkGateway() {
        $url = $this->gatewayUrl . "/api/rest/version/1/information";
        $response = file_get_contents($url);
        return json_decode($response, true);
    }

    private function removeEmptyValues($array) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->removeEmptyValues($value);
                if (empty($array[$key])) unset($array[$key]);
            } else {
                if ($value === "" || $value === null) unset($array[$key]);
            }
        }
        return $array;
    }

    private function createRequestBody($formData) {
        $filteredData = $this->removeEmptyValues($formData);
        return json_encode($filteredData);
    }

    public function sendPaymentRequest($orderId, $transactionId, $transactionData) {
        $url = "{$this->gatewayUrl}/api/rest/version/{$this->version}/merchant/{$this->merchantId}/order/{$orderId}/transaction/{$transactionId}";

        $requestBody = $this->createRequestBody($transactionData);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json; charset=UTF-8",
            "Content-Length: "
             . strlen($requestBody)
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->apiUsername}:{$this->apiPassword}");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ["response" => json_decode($response, true), "http_code" => $httpCode];
    }
}

$merchantId = "TEST9170372718";
$apiUsername = "merchant.TEST9170372718";
$apiPassword = "b56444324642b7e2712b89e1925308fa";
$gatewayUrl = "https://nationstrustbankplc.gateway.mastercard.com";
$version = "81";

$payment = new DirectPayment($merchantId, $apiUsername, $apiPassword, $gatewayUrl, $version);

$gatewayStatus = $payment->checkGateway();
if ($gatewayStatus['status'] !== 'OPERATING') {
    die("Gateway is not available.");
}

$transactionData = [
    "apiOperation" => "PAY",
    "order" => [
        "amount" => $amount_p,
        "currency" =>$currency_p
    ],
    "sourceOfFunds" => [
        "type" => "CARD",
        "provided" => [
            "card" => [
                "number" => $cardNumber,
                "expiry" => [
                    "month" => $expMonth, 
                    "year" => $expYear
                ],
                "securityCode" => $cvv
            ]
        ]
    ]
];

$orderId = "ORDER12345";
$transactionId = uniqid();
$paymentResponse = $payment->sendPaymentRequest($orderId, $transactionId, $transactionData);

if ($paymentResponse['http_code'] === 200) {
    echo "Payment successful: ";
    print_r($paymentResponse['response']);
} else {
    echo "Payment failed: ";
    print_r($paymentResponse['response']);
}
?>
