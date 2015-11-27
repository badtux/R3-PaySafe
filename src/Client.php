<?php
namespace R3Pay;

use R3Pay\Exception\ClientException;

class Client {

    static protected $baseUrl = 'http://{account}.rype3.net/';
    static protected $apipath = 'pay/api/v1';

    protected $guzzleClient;

    public function __construct($domain)
    {
         if(!isset($domain)) {
             throw new ClientException('Rype3 account domain required');
         }

        $this->guzzleClient = new \GuzzleHttp\Client([
            'base_uri' => str_replace('{account}',$domain,static::$baseUrl)
        ]);
    }

    public function getRedirectUri(array $data)
    {
        $path   = self::$apipath.'/me';
        $output = $this->output($this->guzzleClient->request('POST',$path,['json' =>$data]));
        if($output->status){

            return new Response($output->result);
        }

        throw new ClientException($output->errors->message);
    }

    protected function output($response, $body=true)
    {
        return $body ? json_decode($response->getBody()) : $response;
    }
} 