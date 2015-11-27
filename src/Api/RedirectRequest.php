<?php
namespace R3Pay\Api;

use R3Pay\Client;

class RedirectRequest {

    protected $data = [
        'transaction' => [
            'amount' => 0,
            'currency' => 'USD'
        ],
        'invoiceid' => '',
        'customer' => [
            'name'  => '',
            'email' => ''
        ],
        'address' => [
            'street_address' => '',
            'street_address_2' => '',
            'city' => '',
            'country' => ''
        ],
        'returnurl' =>''
    ];

    public function __construct()
    {

    }

    public function setTransaction(Transaction $transaction)
    {
        $this->data['transaction']['amount'] = $transaction->getAmount();
        $this->data['transaction']['currency'] = $transaction->getCurrency();
    }

    public function setInvoice(Invoice $invoice)
    {
        $this->data['invoiceid'] = $invoice->getId();
    }

    public function setCustomer(Customer $customer)
    {
        $this->data['customer']['name'] =  $customer->getName();
        $this->data['customer']['email'] = $customer->getEmail();
    }

    public function setAddress(Address $address)
    {
        $this->data['address']['street_address'] = $address->getStreetAddress();
        $this->data['address']['street_address_2'] = $address->getStreetAddress2();
        $this->data['address']['city'] = $address->getCity();
        $this->data['address']['country'] = $address->getCountry();
    }

    public function setReturnUrl($url)
    {
        $this->data['returnurl'] = $url;
    }

    public function execute($domain)
    {
        $client = new Client($domain);
        return $client->getRedirectUri($this->data);
    }
} 