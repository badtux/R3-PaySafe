<?php
namespace R3Pay;

class PaymentStatusResponse {

    const TXN_REF = 'transaction_id';
    const RES_CODE = 'code';
    const RES_TEXT = 'description';
    const STATUS = 'status';
    const PAYER  = 'payer';


    protected $data;

    public function __construct($response)
    {
        $this->data = $response;
    }

    public function getStatus()
    {
        return $this->getField(self::STATUS);
    }

    public function getResponseCode()
    {
        return $this->getField(self::RES_CODE);
    }

    public function getResponseText()
    {
        return $this->getField(self::RES_TEXT);
    }

    public function getTXNReference()
    {
        return $this->getField(self::TXN_REF);
    }

    protected function getField($field)
    {
        if (array_key_exists($field,$this->data)) {

            return $this->data[$field];
        }
    }

}