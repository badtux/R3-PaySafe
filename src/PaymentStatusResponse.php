<?php
namespace R3Pay;

class PaymentStatusResponse {

    const MERCHANT_RESPONSE = 'merchant_response';
    const TXN_REF = 'txn_reference';
    const RES_CODE = 'response_code';
    const RES_TEXT = 'response_text';
    const SETTLEMENT_DATE = 'settlement_date';
    const AUTH_CODE = 'auth_code';
    const TOKEN = 'token';
    const TOKEN_RESPONSE = 'token_response';


    protected $data;

    public function __construct($response)
    {
        $this->data = $response;
    }

    public function getResponseCode()
    {
        return $this->getField(self::RES_CODE);
    }

    public function getResponseText()
    {
        return $this->getField(self::RES_TEXT);
    }

    public function getSettlementDate()
    {
        return $this->getField(self::SETTLEMENT_DATE);
    }

    public function getAuthCode()
    {
        return $this->getField(self::AUTH_CODE);
    }

    public function getToken()
    {
        return $this->getField(self::TOKEN);
    }

    public function getTokenResponse()
    {
        return $this->getField(self::TOKEN_RESPONSE);
    }

    public function getTXNReference()
    {
        return $this->getField(self::TXN_REF);
    }

    protected function getField($field)
    {
        if (array_key_exists($field,$this->data[self::MERCHANT_RESPONSE])) {

            return $this->data[self::MERCHANT_RESPONSE][$field];
        }
    }

}