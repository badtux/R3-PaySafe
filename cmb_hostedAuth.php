<?php
require_once 'config/config.php';
require 'vendor/autoload.php';

class PaymentcmbSession {
    private $amount;
    private $currency;
    private $description;
    private $orderId;

    public function __construct($details) {
        $this->amount = $details['amount'] ?? '1.00';
        $this->currency = $details['currency'] ?? 'LKR';
        $this->description = $details['description'] ?? 'TEST ORDER';
        
        $_SESSION['orderId'] = $this->orderId;
        $_SESSION['orderId'] = $this->orderId = $details['orderId'] == '' ? 'ORDR' . time() : $details['orderId'];
    }

    public function getSessionId() {
        $merchantId = $this->currency == 'LKR' ? MERCHANT_ID_LKR : MERCHANT_ID_USD;
        $apiUserName = $this->currency == 'LKR' ? API_USERNAME_LKR : API_USERNAME_USD;
        $apiPassWord = $this->currency == 'LKR' ? API_PASSWORD_LKR : API_PASSWORD_USD;

        $url = "https://cbcmpgs.gateway.mastercard.com/api/nvp/version/61";

        $data = http_build_query([
            'apiOperation' => 'CREATE_CHECKOUT_SESSION',
            'apiUsername' => $apiUserName,
            'apiPassword' => $apiPassWord,
            'merchant' => $merchantId,
            'order.id' => $this->orderId,
            'order.amount' => $this->amount,
            'order.currency' => $this->currency,
            'order.description' => $this->description,
            'interaction.operation' => 'PURCHASE',
            'interaction.returnUrl' => REDIRECT_URL,
            'interaction.merchant.name' => NAME
        ]);

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/x-www-form-urlencoded",
                "Cache-Control: no-cache"
            ],
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
            throw new Exception("cURL error: " . $error_msg);
        }
        curl_close($ch);

        parse_str($response, $result);
        if (isset($result['session_id'])) {
            return $result['session_id'];
        } else {
            throw new Exception("Failed to create session: " . json_encode($result));
        }
    }
}
?>
