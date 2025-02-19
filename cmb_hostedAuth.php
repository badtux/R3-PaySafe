
<?php

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
