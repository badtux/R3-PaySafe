<?php


$name = isset($_POST['name']) ? $_POST['name'] : 'Customer';
$reference = isset($_POST['reference']) ? $_POST['reference'] : 'No reference';
$email = isset($_POST['email']) ? $_POST['email'] : '';
$price = isset($_POST['price']) ? floatval($_POST['price']) : 0.0;
$currency_p = isset($_POST['currency']) ? strtoupper($_POST['currency']) : 'LKR';
$cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
$expMonth = str_pad($_POST['exp_month'] ?? '', 2, '0', STR_PAD_LEFT);
$expYear =($_POST['exp_year'] ?? '');
$cvv = preg_replace('/\D/', '', $_POST['cvv'] ?? '');
$amount_p = $price > 0 ? intval($price * 100) : 0;

$merchantId = "TEST9170372718";
$apiUsername = "merchant.TEST9170372718";
$apiPassword = '9561cde89b146e22afd2dbec7d145a4f';
$gatewayUrl = "https://nationstrustbankplc.gateway.mastercard.com";
$version = "81";

$orderId = uniqid();
$transactionId = uniqid();


// $apiUrl = "https://na-gateway.mastercard.com/api/rest/version/81/merchant/$merchantId/token/";

// $data = [
//     "billing" => [
//         "address" => [
//             "street" => "1 east street",
//             "city" => "1234",
//             "stateProvince" => "08",
//             "postcodeZip" => "2012",
//             "country" => "AUS"
//         ]
//     ],
//     "sourceOfFunds" => [
//         "provided" => [
//             "card" => [
//                 "number" =>$cardNumber,
//                 "expiry" => [
//                     "month" => $expMonth,
//                     "year" => $expYear,
//                 ],
//                 "nameOnCard" => "Bill Smith Jones"
//             ]
//         ],
//         "type" => "CARD"
//     ],
//     "customer" => [
//         "firstName" => "Bob",
//         "lastName" => "Smith",
//         "taxRegistrationId" => "tax-id-133",
//         "email" => "mark@mc.com.fu",
//         "mobilePhone" => "0493049",
//         "phone" => "9320490"
//     ],
//     "transaction" => [
//         "currency" => "USD"
//     ]
// ];

// $ch = curl_init($apiUrl);
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// curl_setopt($ch, CURLOPT_USERPWD, "merchant.$merchantId:$apiPassword");
// curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
// curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
// //curl_setopt($ch, CURLOPT_POST, true);
// curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); 

// curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

// $response = curl_exec($ch);
// curl_close($ch);

// echo $response; 
// error_log($response);







class DirectPayment
{
    private $merchantId;
    private $apiUsername;
    private $apiPassword;
    private $gatewayUrl;
    private $version;

    public function __construct($merchantId, $apiUsername, $apiPassword, $gatewayUrl, $version = "81")
    {
        $this->merchantId = $merchantId;
        $this->apiUsername = $apiUsername;
        $this->apiPassword = $apiPassword;
        $this->gatewayUrl = $gatewayUrl;
        $this->version = $version;
    }

    public function checkGateway()
    {
        $url = $this->gatewayUrl . "/api/rest/version/81/information";
        $response = file_get_contents($url);
        return json_decode($response, true);
    }
    private function removeEmptyValues($array)
    {
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

    private function createRequestBody($formData)
    {
        $filteredData = $this->removeEmptyValues($formData);
        return json_encode($filteredData);
    }
    public function sendPaymentRequest($orderId, $transactionId, $transactionData)
    {
        $url = "{$this->gatewayUrl}/api/rest/version/{$this->version}/merchant/{$this->merchantId}/order/{$orderId}/transaction/{$transactionId}";

        $requestBody = $this->createRequestBody($transactionData);
        error_log($requestBody);
        error_log($url);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json; charset=UTF-8",
            "Content-Length: " . strlen($requestBody)
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->apiUsername}:{$this->apiPassword}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); 
       // curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ["response" => json_decode($response, true), "http_code" => $httpCode];
    }
}

error_log($expMonth);

$payment = new DirectPayment($merchantId, $apiUsername, $apiPassword, $gatewayUrl, $version);

$gatewayStatus = $payment->checkGateway();
if ($gatewayStatus['status'] !== 'OPERATING') {
    die("Gateway is not available.");
}

$transactionData = [
    "apiOperation" => "PAY",
    "order" => [
        "amount" => $amount_p,
        "currency" => $currency_p
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

$paymentResponse = $payment->sendPaymentRequest($orderId, $transactionId, $transactionData);

if ($paymentResponse['http_code'] === 200) {
    echo "Payment successful: ";
    print_r($paymentResponse['response']);
} else {
    echo "Payment failed: ";
    print_r($paymentResponse['response']);
}                            
  
