<?php
require_once 'cmb_hostedAuth.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
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


</body>
</html>
