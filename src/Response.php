<?php
namespace R3Pay;

class Response {

    protected $data;

    public function __construct($response)
    {
        $this->data = $response;
    }
} 