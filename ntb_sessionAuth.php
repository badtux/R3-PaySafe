<?php
require_once 'config/config.php';
//require_once 'config/config.sample.php';
require 'vendor/autoload.php';

class ntbToken {
    private $amount = 0;
    private $orderId = '';
    private $description = 'N/A';
    private $currency = 'USD';

    public function setOrderDetails(array $details){
        $this->amount = $details['amount'];
        $this->currency = $details['currency'];
        $this->description = $details['description'];
        $_SESSION['orderId'] = $this->orderId = $details['orderId'] == '' ? 'ORDR' . time() : $details['orderId'];
    
    }

    public function getOrderId() {
        return $this->orderId;
    }
    public function getAmount() {
        return $this->amount;
    }
    public function getDescreption() {
        return $this->description;
    }
    public function getCurrency() {
        return $this->currency;
    }

    public function getSessionId() {
        $sessionId = null;
        
        $url = 'https://nationstrustbankplc.gateway.mastercard.com/api/rest/version/81/merchant/'.MERCHANT_ID.'/session';

        $data = [
            "apiOperation" => "INITIATE_CHECKOUT",
            "checkoutMode" => "WEBSITE",
            "interaction" => [
                "operation" => "AUTHORIZE",
                "merchant" => [
                    "name" => NAME,
                    "logo" => LOGO,
                    "url" => "https://www.malkey.lk",
                    "phone" => "+94-112365365",
                    "email" => "info@malkey.lk"
                ],
                "returnUrl" => RETURN_URL,
                "locale" => "en_US",
                "style" => [
                    "theme" => "default"
                ]
            ],
            "order" => [
                "currency" => $this->currency,
                "amount" => $this->amount,
                "id" => $this->orderId,
                "description" => $this->description
            ]
        ];

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                "Content-Type: text/plain",
                "Authorization: Basic " . base64_encode(API_USERNAME . ":" . API_PASSWORD)
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
            throw new Exception('cURL error: : ' . $error_msg, 8);
        }
        curl_close($ch);

        $result = json_decode($response, true);

        if (isset($result['session']['id'])) {
            return $result['session']['id'];
        } else {
            throw new Exception('Failed to create session: ' . json_encode($result), 9);
        }
    }
}