<?php
namespace R3Pay\Api;

use R3Pay\Validator\NumericValidator;

class Transaction {

    protected $amount = 0;

    protected $currency = 'USD';
    /**
     * @var \R3Pay\Api\Customer
     */
    protected $customer;
    /**
     * @var \R3Pay\Api\Address
     */
    protected $address;
    /**
     * @var \R3Pay\Api\Invoice
     */
    protected $invoice;

    protected $returnurl;

    /**
     * @var \R3Pay\Api\ItemList
     */
    protected $itemlist;

    /**
     * @return mixed
     */
    public function getReturnurl()
    {
        return $this->returnurl;
    }

    /**
     * @param mixed $returnurl
     */
    public function setReturnurl($returnurl)
    {
        $this->returnurl = $returnurl;
        return $this;
    }

    public function __construct()
    {

    }

    /**
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param int $amount
     */
    public function setAmount($amount)
    {
        NumericValidator::validate($amount,'Total Amount');
        $this->amount = $amount;
        return $this;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    public function setCustomer(Customer $customer)
    {
        $this->customer = $customer;
        return $this;
    }

    public function setAddress(Address $address)
    {
        $this->address = $address;
        return $this;
    }

    public function setInvoice(Invoice $invoice)
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function setItemlist(ItemList $itemlist)
    {
        $this->itemlist = $itemlist;
    }

    public function toArray()
    {
        return [
           'transaction' => [
               'amount' => $this->getAmount(),
               'currency' => $this->getCurrency()
           ],
            'invoiceid' => $this->invoice->getId(),
            'itemlist'=> $this->itemlist->toArray(),
            'customer' => [
               'name' => $this->customer->getName(),
               'email' => $this->customer->getEmail()
            ],
            'address' => [
               'street_address' => $this->address->getStreetAddress(),
                'street_address_2' => $this->address->getStreetAddress2(),
                'city' => $this->address->getCity(),
                'state' => $this->address->getState(),
                'country' => $this->address->getCountry()
            ],
            'returnurl' => $this->getReturnurl()
        ];
    }




} 