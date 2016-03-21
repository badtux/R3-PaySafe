## Example
```php
        $client = new Client('malkey');//tenant id

         $customer = new Customer();
         $customer->setEmail('test@rype3.com'); //customer email
         $customer->setName('test'); // customer name

         $invoice = new Invoice();
         $invoice->setId('6721HDt2'); // invoice id

         $address =  new Address();
         $address->setStreetAddress('main street'); // Street address
         $address->setStreetAddress2('main street'); // Street address 2
         $address->setCity('colombo'); // City
         $address->setState('western'); // State
         $address->setCountry('Sri lanka'); // Country

         $item = new Item();
         $item->setName('ItemName');
              ->setQuantity(1);
              ->setPrice(1000);
              ->setCurrency('USD');
              ->setDescription('blah blah');

         $itemlist = new ItemList()
         $itemlist->setItem($item);

         $transaction = new Transaction();
         $transaction->setAmount(50); // Transaction amount
         $transaction->setCurrency('USD'); // Currency Type
         $transaction->setAddress($address); // Set the address
         $transaction->setCustomer($customer); // Set the Customer
         $transaction->setInvoice($invoice); // Set the Invoice
         $transaction->setItemL
         $transaction->setReturnurl('http://malkey.rype3.dev/paysafe/test'); // Set redirect url

         $client->getRedirectUri($transaction) // Get the redirect url
 ```