<?php
$merchantId = "TEST9170372718";
$apiUsername = "merchant.TEST9170372718";
$apiPassword = "b56444324642b7e2712b89e1925308fa";

$orderId = "order#1294";
$description = "test order";

$url = "https://nationstrustbankplc.gateway.mastercard.com/api/rest/version/81/merchant/$merchantId/session";

echo $url;
$data = [
    "apiOperation" => "INITIATE_CHECKOUT",
    "interaction" => [
        "operation" => "AUTHORIZE",
        "merchant" => [
            "name" => "merchant.TEST9170372718"
        ]
    ],
    "order" => [
        "currency" => "USD",
        "amount" => "100.00",
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
    
    curl_close($ch);
    die("cURL error: " . $error_msg); 
}

curl_close($ch);

$result = json_decode($response, true);
if (isset($result['session']['id'])) {
    $sessionId = $result['session']['id'];
   
    echo json_encode(["sessionId" => $sessionId]);
} else {
    die("Failed to create session: " . json_encode($result));
}
?>
