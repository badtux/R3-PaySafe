<?php
require_once "ntb_sessionAuth.php";

if (!isset($sessionId)) {
    die("Session ID not available.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/hosted.css">
    <script src="https://nationstrustbankplc.gateway.mastercard.com/static/checkout/checkout.min.js" 
            data-error="errorCallback" 
            data-cancel="cancelCallback">
    </script>
</head>
<body>
    <div class="container mt-1">
        <div class="text-center">
            <img src="https://d8asu6slkrh4m.cloudfront.net/2013/04/malkey-logo.png" alt="Logo" class="logo">
            <!-- <img src="assets/Makley_logo.png" alt="Logo" class="logo"> -->

        </div>
        <div class="text-center mt-3"></div>
            <button class="btn btn-primary" onclick="Checkout.showPaymentPage();">Redirect to Payment Page</button>
            </div>
        </div>
        
        <div id="embed-target" class="mt-1"></div>
    </div>

    <div class="modal fade" id="paymentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Your Secure Payment</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="hco-embedded"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function errorCallback(error) {
            alert("There was an error: " + JSON.stringify(error));
        }
        function cancelCallback() {
            alert("The payment has been canceled. Please try again.");
        }

        const sessionId = "<?php echo $sessionId; ?>";

        Checkout.configure({
            session: { id: sessionId }
        });

        function startPayment() {
            document.getElementById('startPaymentBtn').style.display = 'none';

            Checkout.showEmbeddedPage('#embed-target');

            $('#paymentModal').modal('show');
        }

        $('#paymentModal').on('shown.bs.modal', function () {
            Checkout.showEmbeddedPage('#hco-embedded', () => $('#paymentModal').modal());
        });

        $('#paymentModal').on('hide.bs.modal', function () {
            sessionStorage.clear();
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
