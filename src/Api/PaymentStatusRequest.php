<?php
namespace R3Pay\Api;

use R3Pay\Client;

class PaymentStatusRequest {

    protected $reqid;

    public function __construct($reqid)
    {
        $this->reqid = $reqid;
    }

    public function getReqId()
    {
        return $this->reqid;
    }

    public function execute($domain)
    {
        $client = new Client($domain);

        return $client->getPaymentStatus($this->getReqId());
    }
}