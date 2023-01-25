<?php 
$md5 = md5(time());
echo $md5;
?>

<html>
    <head>
        <!-- https://cbcmpgs.gateway.mastercard.com/static/checkout/checkout.min.js -->
        <script src="https://cbcmpgs.gateway.mastercard.com/checkout/version/57/checkout.js"
                data-error="errorCallback"
                data-cancel="cancelCallback">
        </script>
    
        <script type="text/javascript">
            function errorCallback(error) {
                  console.log(JSON.stringify(error));
            }
            function cancelCallback() {
                  console.log('Payment cancelled');
            }
         
            Checkout.configure({
                session: {
                    id: 'SESSION000292090496253818604576'
                },
                interaction: {
                    displayControl: {       // you may change these settings as you prefer
                      billingAddress  :  '200 Sample St',  
                      customerEmail   : 'test@test.com',
                      orderSummary    : 'asdasd asdasdasd asdasdasdasd',
                      shipping        : 'shipping'
            	    }
                }
            }); 
        </script>
    </head>
    <body> 
        <div id="embed-target"> </div>
        <input type="button" value="Pay with Embedded Page" onclick="Checkout.showEmbeddedPage('#embed-target');" />
        <input type="button" value="Pay with Payment Page" onclick="Checkout.showPaymentPage();" />
    
     --->
     <input type="button" value="Pay with Lightbox" onclick="Checkout.showLightbox();" />
        <input type="button" value="Pay with Payment Page" onclick="Checkout.showPaymentPage();"/>
    </body>
</html>