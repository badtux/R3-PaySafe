<?php
class PaymentGateway {
    private $apiUrl;
    private $merchantId;
    private $apiPassword;
    private $version;
    private $certificatePath;
    private $curlObj;

    public function __construct($merchantId, $apiPassword, $version, $certificatePath = "") {
        $this->apiUrl = "https://nationstrustbankplc.gateway.mastercard.com/api/rest";
        $this->merchantId = $merchantId;
        $this->apiPassword = $apiPassword;
        $this->version = $version;
        $this->certificatePath = $certificatePath;
    }

    // Check if the gateway is operational
    public function checkGatewayConnectivity() {
        $url = "{$this->apiUrl}/version/1/information";
        
        // Initialize cURL session
        $this->curlObj = curl_init();

        // Set cURL options
        curl_setopt($this->curlObj, CURLOPT_URL, $url);
        curl_setopt($this->curlObj, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
        curl_setopt($this->curlObj, CURLOPT_FOLLOWLOCATION, true); // Follow redirects if any
        curl_setopt($this->curlObj, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification (for testing)

        // Execute the cURL request
        $response = curl_exec($this->curlObj);
        $httpCode = curl_getinfo($this->curlObj, CURLINFO_HTTP_CODE);
        $error = curl_error($this->curlObj);

        // Close cURL session
        curl_close($this->curlObj);

        if ($error) {
            die("❌ Unable to connect to the payment gateway: " . $error);
        }

        // Check the response
        $responseData = json_decode($response, true);

        if (!isset($responseData['status']) || $responseData['status'] !== "OPERATING") {
            die("❌ Gateway is not operating.");
        }

        echo "✅ Payment Gateway is operational.\n";
    }

    // Remove empty values from data
    private function removeEmptyValues($array) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->removeEmptyValues($value);
                if (empty($array[$key])) {
                    unset($array[$key]);
                }
            } elseif ($value === "" || $value === null) {
                unset($array[$key]);
            }
        }
        return $array;
    }

    // Convert form data to JSON format
    private function parseRequest($formData) {
        if (empty($formData)) {
            return "";
        }
        $formData = $this->removeEmptyValues($formData);
        return json_encode($formData, JSON_PRETTY_PRINT);
    }

    // Create request URL
    private function formRequestUrl($customUri) {
        return "{$this->apiUrl}/version/{$this->version}/merchant/{$this->merchantId}{$customUri}";
    }

    // Send transaction request
    public function sendTransactionRequest($endpoint, $formData) {
        $this->curlObj = curl_init();

        $requestJson = $this->parseRequest($formData);
        $requestUrl = $this->formRequestUrl($endpoint);

        curl_setopt($this->curlObj, CURLOPT_URL, $requestUrl);
        curl_setopt($this->curlObj, CURLOPT_POST, 1);
        curl_setopt($this->curlObj, CURLOPT_POSTFIELDS, $requestJson);
        curl_setopt($this->curlObj, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlObj, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json; charset=UTF-8",
            "Content-Length: " . strlen($requestJson)
        ]);

        curl_setopt($this->curlObj, CURLOPT_USERPWD, "{$this->merchantId}:{$this->apiPassword}");

        // SSL Verification Handling
        if (!empty($this->certificatePath)) {
            curl_setopt($this->curlObj, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($this->curlObj, CURLOPT_CAINFO, $this->certificatePath);
        } else {
            curl_setopt($this->curlObj, CURLOPT_SSL_VERIFYPEER, false);
        }

        $response = curl_exec($this->curlObj);
        $httpCode = curl_getinfo($this->curlObj, CURLINFO_HTTP_CODE);
        $error = curl_error($this->curlObj);

        curl_close($this->curlObj);

        if ($error) {
            return ["status_code" => $httpCode, "error" => $error, "response" => null];
        }

        return ["status_code" => $httpCode, "response" => json_decode($response, true)];
    }
}

// Initialize Payment Gateway
$merchantId = "TEST9170372718";
$apiPassword = "9561cde89b146e22afd2dbec7d145a4f";
$version = "1";
$certificatePath = ""; // Set path to SSL certificate if required

$gateway = new PaymentGateway($merchantId, $apiPassword, $version, $certificatePath);

// Check gateway connectivity
$gateway->checkGatewayConnectivity();

// Sample Transaction Data
$transactionData = [
    "order" => [
        "id" => "ORDER123456",
        "currency" => "USD",
        "amount" => 1000
    ],
    "transaction" => [
        "reference" => "TXN123456",
        "type" => "PAYMENT"
    ],
    "sourceOfFunds" => [
        "provided" => [
            "card" => [
                "number" => "4111111111111111",
                "expiry" => [
                    "month" => "12",
                    "year" => "2025"
                ],
                "securityCode" => "123"
            ]
        ]
    ]
];

// Send Payment Request
$response = $gateway->sendTransactionRequest("/order/ORDER123456/transaction", $transactionData);

// Display Response
echo "🔹 HTTP Status Code: " . $response["status_code"] . "\n";

if (isset($response["error"])) {
    echo "❌ Error: " . $response["error"] . "\n";
} else {
    echo "🔹 Response: " . json_encode($response["response"], JSON_PRETTY_PRINT) . "\n";
}
?>
