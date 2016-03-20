<?php
namespace R3Pay;

use R3Pay\Api\Transaction;
use R3Pay\Exception\ClientException;

class Client {

    static protected $baseUrl = 'http://{account}.rype3.net/';
    static protected $apipath = 'paysafe/api/v1';

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

    public function getRedirectUri(Transaction $transaction)
    {
        $path   = self::$apipath.'/redirect';
        $output = $this->output($this->guzzleClient->request('POST',$path,['json' =>$transaction->toArray(),
            ['headers' =>[
                'Origin' =>getenv('SERVER_NAME'),
            ]]]));
        if($output['status']){

            return new RedirectResponse($output['result']);
        }

        throw new ClientException($output['errors']['message']);
    }

    public function getPaymentStatus($reqid)
    {
        $path   = self::$apipath.'/status/'.$reqid;
        $output = $this->output($this->guzzleClient->request('POST',$path,['json' =>[]]));
        if($output['status']){

            return new PaymentStatusResponse($output['result']);
        }

        throw new ClientException($output['message']);
    }

    protected function output($response, $body=true)
    {
        return $body ? json_decode($response->getBody(),true) : $response;
    }
} 