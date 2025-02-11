
<?php

class Merchant {
    private $gatewayUrl;
    private $version;
    private $merchantId;
    private $apiPassword;
    private $certificatePath;
    private $certificateVerifyPeer = true;
    private $certificateVerifyHost = 2;
    private $proxyServer;
    private $proxyAuth;

    public function __construct($config) {
        $this->gatewayUrl = $config['gatewayUrl'];
        $this->version = $config['version'];
        $this->merchantId = $config['merchantId'];
        $this->apiPassword = $config['apiPassword'];
        $this->certificatePath = $config['certificatePath'] ?? '';
        $this->proxyServer = $config['proxyServer'] ?? '';
        $this->proxyAuth = $config['proxyAuth'] ?? '';
    }

    // Getters
    public function GetGatewayUrl() { return $this->gatewayUrl; }
    public function GetVersion() { return $this->version; }
    public function GetMerchantId() { return $this->merchantId; }
    public function GetPassword() { return $this->apiPassword; }
    public function GetCertificatePath() { return $this->certificatePath; }
    public function GetCertificateVerifyPeer() { return $this->certificateVerifyPeer; }
    public function GetCertificateVerifyHost() { return $this->certificateVerifyHost; }
    public function GetProxyServer() { return $this->proxyServer; }
    public function GetProxyAuth() { return $this->proxyAuth; }
}

class Connection {
    private $curlObj;

    public function __construct() {
        $this->curlObj = curl_init();
        curl_setopt($this->curlObj, CURLOPT_RETURNTRANSFER, true);
    }

    public function RemoveEmptyValues($array) {
        foreach ($array as $i => $value) {
            if (is_array($array[$i])) {
                if (count($array[$i]) === 0) {
                    unset($array[$i]);
                } else {
                    $array[$i] = $this->RemoveEmptyValues($array[$i]);
                    if (count($array[$i]) === 0) {
                        unset($array[$i]);
                    }
                }
            } else {
                if ($array[$i] === "" || $array[$i] === null) {
                    unset($array[$i]);
                }
            }
        }
        return $array;
    }

    public function ParseRequest($formData) {
        $cleanedData = $this->RemoveEmptyValues($formData);
        return json_encode($cleanedData, JSON_UNESCAPED_SLASHES);
    }

    public function FormRequestUrl(Merchant $merchant, $customUri) {
        $url = $merchant->GetGatewayUrl() 
              . "/version/" . $merchant->GetVersion()
              . "/merchant/" . $merchant->GetMerchantId()
              . $customUri;
        return $url;
    }

    public function SendTransaction(Merchant $merchant, $requestBody, $customUri, $method = 'POST') {
        try {
            // Prepare request URL
            $url = $this->FormRequestUrl($merchant, $customUri);
            curl_setopt($this->curlObj, CURLOPT_URL, $url);

            // Set authentication
            curl_setopt($this->curlObj, CURLOPT_USERPWD, $merchant->GetMerchantId() . ":" . $merchant->GetPassword());

            // Set headers
            $headers = [
                "Content-Type: application/json;charset=UTF-8",
                "Accept: application/json"
            ];
            curl_setopt($this->curlObj, CURLOPT_HTTPHEADER, $headers);
            
            if ($method === 'POST') {
                curl_setopt($this->curlObj, CURLOPT_POST, true);
            } else {
                curl_setopt($this->curlObj, CURLOPT_CUSTOMREQUEST, $method);
            }
            curl_setopt($this->curlObj, CURLOPT_POSTFIELDS, $requestBody);

         
            if ($merchant->GetCertificatePath()) {
                curl_setopt($this->curlObj, CURLOPT_CAINFO, $merchant->GetCertificatePath());
            }
            curl_setopt($this->curlObj, CURLOPT_SSL_VERIFYPEER, $merchant->GetCertificateVerifyPeer());
            curl_setopt($this->curlObj, CURLOPT_SSL_VERIFYHOST, $merchant->GetCertificateVerifyHost());

          
            if ($merchant->GetProxyServer()) {
                curl_setopt($this->curlObj, CURLOPT_PROXY, $merchant->GetProxyServer());
                if ($merchant->GetProxyAuth()) {
                    curl_setopt($this->curlObj, CURLOPT_PROXYUSERPWD, $merchant->GetProxyAuth());
                }
            }

            // Execute request
            $response = curl_exec($this->curlObj);
            $httpCode = curl_getinfo($this->curlObj, CURLINFO_HTTP_CODE);
            
            if ($response === false) {
                throw new Exception('Curl error: ' . curl_error($this->curlObj));
            }

            return [
                'status' => $httpCode,
                'response' => json_decode($response, true)
            ];

        } finally {
            curl_close($this->curlObj);
        }
    }
}
