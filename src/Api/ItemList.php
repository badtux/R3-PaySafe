<?php
namespace R3Pay\Api;

class ItemList {

    protected $data;

    public function setItems(Item $item)
    {
        $this->data['itemlist'][] =[
            'name'  => $item->getName(),
            'price' => $item->getPrice(),
            'currency' => $item->getCurrency(),
            'description' => $item->getDescription(),
            'quantity' => $item->getQuantity()
        ];
    }

    public function toArray()
    {
        if(!isset($this->data['itemlist'])) {

            return [];
        }

        return $this->data['itemlist'];
    }
} 