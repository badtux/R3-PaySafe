<?php
namespace R3Pay;

class RedirectResponse {

    const REQID = 'reqid';
    const EXPIRYTIME = 'expiry_time';
    const REDIRECT_URL = 'redirect_url';

    protected $data;

    public function __construct($response)
    {
        $this->data = $response;
    }

    public function getReqId()
    {
        return $this->getField(self::REQID);
    }

    public function getExpiryTime()
    {
        return $this->getField(self::EXPIRYTIME);
    }

    public function getRediectUrl()
    {
        return $this->getField(self::REDIRECT_URL);
    }

    protected function getField($field)
    {
        if (array_key_exists($field,$this->data)) {

            return $this->data[$field];
        }
    }
} 