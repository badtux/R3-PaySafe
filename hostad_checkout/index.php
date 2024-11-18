
<?php

// curl -X POST \
// https://cbcmpgs.gateway.mastercard.com/api/nvp/version/61 \
// -H 'cache-control: no-cache' \
// -H 'content-type: application/x-www-form-urlencoded' \
// -H 'postman-token: 761faf3f-6d97-5200-fc62-96e4a9221ede' \
// -d
// 'apiOperation=CREATE_CHECKOUT_SESSION
// apiUsername=merchant.TESTXXXXXXLKR
// apiPassword=XXXXXXXXXXXXXXXXXXXXXXXXX
// merchant=TESTXXXXXLKR
// order.id=10601
// order.amount=11.00
// oder.currency=LKR
// order.description=order_10601&interaction.operation=PURCHASE&interaction.retu
// rnUrl=https%3A%2F%2Faaaaaa.com%2FSuccessPage&interaction.merchant.name=ABCD%20company %20(pvt)%20ltd';


// url : 'http://cmbgateway.loc/';
// url : 'https://cbcmpgs.gateway.mastercard.com/api/nvp/version/61';


$orderid = "10601";
$apiPassword = "CBCTEST";
$merchant = "TESTMALKEYRENLKR";
$amount = "11.00";
//$returnUrl = "http://cmbgateway.loc/";
$currency = "LKR";

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'https://cbcmpgs.gateway.mastercard.com/api/nvp/version/57');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);

curl_setopt(
    $ch,
    CURLOPT_POSTFIELDS,
    "apiOperation=CREATE_CHECKOUT_SESSION&" .
        "apiPassword=$apiPassword&" .
        "interaction.returnUrl=$returnUrl&" .
        "interaction.operation=PURCHASE&" .
        "apiUsername=merchant.$merchant&" .
        "merchant=$merchant&" .
        "order.id=$orderid&" .
        "order.amount=$amount&" .
        "order.currency=$currency"
);

$headers = array();
$headers[] = 'Content-Type: application/x-www-form-urlencoded';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'ERROR: ' . curl_error($ch);
} else {
    echo $result;
}
curl_close($ch);

curl_close($ch);
print_r($ch);


$sessionid = explode("=", explode("&", $result)[2])[1];


?>


<script src="https://cbcmpgs.gateway.mastercard.com/checkout/version/61/checkout.js"
    data-error="errorCallback"
    data-cancel="http://cmbgateway.loc/config/indoooooooooooex.php">
</script>
<script type="text/javascript">
    function errorCallback(error) {
        alert("Error:" + JSON.stringify(error));
        window.location.href = "http://cmbgateway.loc/config/index.php"

    }

    Checkout.configure({
        merchant: '<?php echo $merchant ?>',
        order: {
            amount: function() {
                return <?php echo $amount; ?>;

            },
            currency: <?php echo $currency; ?>;
            description: 'Order Goods',
            id: '<?php echo $orderid; ?>',

        },
        interaction: {
            merchant: {
                name: 'mohan joe',
                address: {
                    line1: '1234',
                    line2: 'colombo',

                }
            },
        },
        session: {
            id: '<?php echo $sessionid; ?>'
        }

    });
    Checkout.showPaymentPage();
</script>

?>