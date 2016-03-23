<?php
namespace R3Pay\Api;

class Payer {

    protected $holdername;
    protected $cardno;
    protected $card_type;
    protected $card_expiry;


    /**
     * @return mixed
     */
    public function getCardExpiry()
    {
        return $this->card_expiry;
    }

    /**
     * @param mixed $card_expiry
     */
    public function setCardExpiry($card_expiry)
    {
        $this->card_expiry = $card_expiry;
    }

    /**
     * @return mixed
     */
    public function getCardType()
    {
        return $this->card_type;
    }

    /**
     * @param mixed $card_type
     */
    public function setCardType($card_type)
    {
        $this->card_type = $card_type;
    }

    /**
     * @return mixed
     */
    public function getCardno()
    {
        return $this->cardno;
    }

    /**
     * @param mixed $cardno
     */
    public function setCardno($cardno)
    {
        $this->cardno = $cardno;
    }

    /**
     * @return mixed
     */
    public function getHoldername()
    {
        return $this->holdername;
    }

    /**
     * @param mixed $holdername
     */
    public function setHoldername($holdername)
    {
        $this->holdername = $holdername;
    }


} 