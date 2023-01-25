<?php
    $username = 'merchant.TESTMALKEYRENLKR';
    $password = '0778afc55fa88712010a6e258f60c565';
    $merchant = 'TESTMALKEYRENLKR';
    $sessionId = false;
    $currency = 'LKR';
    $amount = 10.00;
    $uniqueOrderId = (string)rand(10, 99);
    $curl = curl_init(); 
     
    $headers = [
        'Authorization' => 'Basic '.base64_encode($username.':'.$password),
        'Content-Type' => 'application/json',
        // 'Content-Type' => 'application/x-www-form-urlencoded'
    ];
 
    
    curl_setopt($curl, CURLOPT_URL, "https://cbcmpgs.gateway.mastercard.com/api/nvp/version/57");
    // curl_setopt($curl, CURLOPT_URL, "https://cbcmpgs.gateway.mastercard.com/api/rest/version/69/merchant/$merchant/session");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTPHEADER,  $headers);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, "apiOperation=INITIATE_CHECKOUT&apiPassword=$password&apiUsername=$username&merchant=$merchant&interaction.operation=AUTHORIZE&order.id=$uniqueOrderId&order.amount=$amount&order.currency=$currency");
    
    $result = curl_exec($curl);

    if(curl_errno($curl)){
        echo `ERROR : `, curl_error($curl);
    }
    curl_close($curl);
    print_r($result);
    // $sessionId = explode("=", explode("&", $result)[2])[1];
?> 

<script src="https://cbcmpgs.gateway.mastercard.com/static/checkout/checkout.min.js" data-error="errorCallback" data-cancel="cancelCallback"></script>
<!-- <!-- 
<script>
    function errorCallback(error) {
        console.log(JSON.stringify(error));
        
    }
    function cancelCallback() {
        console.log('Payment cancelled');
        alert('Payment cancelled');
    }

    Checkout.configure({
        session: {
            id: '<?=$sessionId?>'
        },
        interaction: {
            merchant: {
                name: 'MALKEY',
                address: {
                    line1: '1',
                    line2: '1234 Example Town'            
                }    
            }
        }
    });
    
    Checkout.showPaymentPage() -->
</script> -->