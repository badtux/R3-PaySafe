<?php

$amount = isset($_GET['amount']) ? $_GET['amount'] : "1.00";
$currency = isset($_GET['currency']) ? $_GET['currency'] : "USD";
$description = isset($_GET['description']) ? $_GET['description'] : "no description";
$orderId =  isset($_GET['orderId']) ? $_GET['orderId'] : "no order id";
$merchantId = "TEST9170372718";
$apiUsername = "merchant.TEST9170372718";
$apiPassword = "9561cde89b146e22afd2dbec7d145a4f";

$url = "https://nationstrustbankplc.gateway.mastercard.com/api/rest/version/81/merchant/$merchantId/session";

$data = [
    "apiOperation" => "INITIATE_CHECKOUT",
    "checkoutMode" => "WEBSITE",
    "interaction" => [
        "operation" => "AUTHORIZE",
        "merchant" => [
            "name" => "merchant.TEST9170372718",
            "logo" =>  "https://static.wixstatic.com/media/c7b147_b3d1abb02b5346b68d176a13f1ae27d5~mv2.jpg/v1/fill/w_847,h_807,al_c,q_85/Malkey%20Logo%20Red%20-%20Milindu%20Mallawaratchie.jpg",
           // "logo" =>  "https://findit-resources.s3.us-east-2.amazonaws.com/account/profilePictures/1628852770756.png",

          
            "url" => "https://www.malkey.lk",
            "phone" => "+94-112365365",
            "email" => "info@malkey.lk"
        ],
        //"returnUrl" => "https://www.malkey.lk",

        "locale" => "en_US",
        "style" => [
            "theme" => "default",


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
        "Authorization: Basic " . base64_encode("$apiUsername:$apiPassword")
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

    // echo json_encode(["sessionId" => $sessionId]);
} else {
    die("Failed to create session: " . json_encode($result));
}
