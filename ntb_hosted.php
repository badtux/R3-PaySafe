<?php
require_once "ntb_sessionAuth.php";

if (!isset($sessionId)) {
    die("Session ID not available.");
}
$amount = isset($_GET['amount']) ? $_GET['amount'] : "1.00";
$currency = isset($_GET['currency']) ? $_GET['currency'] : "USD";
$description = isset($_GET['description']) ? $_GET['description'] : "No description available.";
$orderId = isset($_GET['orderId']) ? $_GET['orderId'] : "No order ID available.";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment | Nations Trust Bank</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.2/css/boxicons.min.css" rel="stylesheet">
    <script src="https://nationstrustbankplc.gateway.mastercard.com/static/checkout/checkout.min.js">
    </script>
</head>

<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen flex items-center justify-center p-4">
    <div id="main-container" class="bg-white rounded-2xl shadow-2xl transition-all duration-300 hover:shadow-xl w-full max-w-lg overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 text-center">
            <img src="https://d8asu6slkrh4m.cloudfront.net/2013/04/malkey-logo.png" alt="Logo" class="w-40 h-19 mx-auto mb-2 filter brightness-0 invert">
            <h1 class="text-2xl font-bold text-blue-100">Secure Payment</h1>
            <p class="text-blue-100 text-sm">Protected by Nations Trust Bank</p>
        </div>
        <div id="main_2">
            <div class="px-6 pt-8">
                <div class="space-y-6 mb-8">
                    <div class="flex items-center space-x-4 bg-blue-50 p-4 rounded-xl">
                        <i class='bx bx-receipt text-2xl text-blue-600'></i>
                        <div class="text-left">
                            <p class="text-sm text-gray-500">Order Reference</p>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($orderId); ?></p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4 bg-blue-50 p-4 rounded-xl">
                        <i class='bx bx-detail text-2xl text-blue-600'></i>
                        <div class="text-left">
                            <p class="text-sm text-gray-500">Description</p>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($description); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4 bg-blue-50 p-4 rounded-xl">
                        <i class='bx bx-credit-card text-2xl text-blue-600'></i>
                        <div class="text-left">
                            <p class="text-sm text-gray-500">Total Amount</p>
                            <p class="font-bold text-2xl text-blue-600">
                                <?php echo htmlspecialchars($currency) . ' ' . htmlspecialchars($amount); ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4 bg-blue-50 p-4 rounded-xl">
                        <i class='bx bx-detail text-2xl text-blue-600'></i>
                        <div class="text-left w-full">
                            <p class="text-sm text-gray-500">Your Email</p>
                            <input type="text" id="email" class="border-2 border-gray-300 p-2 rounded-lg w-full" placeholder="Enter your email">
                            <p id="error-message" class="text-red-500 text-sm mt-1 hidden">Please enter a valid email.</p>
                        </div>
                    </div>


                </div>
                <button onclick="validateAndProceed()"
                    class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] shadow-lg hover:shadow-blue-200 flex items-center justify-center space-x-2">
                    <i class='bx bx-lock-alt text-xl'></i>
                    <span>Proceed to Secure Payment</span>
                </button>


            </div>
        </div>

        <div class="mt-3 mb-6 flex items-center justify-center text-sm text-gray-500">
            <div class="flex items-center">
                <i class='bx bx-shield-quarter text-green-500'></i>
                <span class="mr-2">256-bit SSL Secured Connection</span>
            </div>
            <div>
                <img src="assets/card.png" alt="bank logo" class="h-10 ">
            </div>
        </div>

    </div>
    <script>
        function validateEmail(email) {
            const re = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            return re.test(email);
        }
        const sessionId = "<?php echo $sessionId; ?>";
        Checkout.configure({
            session: {
                id: sessionId
            }
        });

        function validateAndProceed() {
            let email = document.getElementById("email").value;
            let emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; // Basic email regex
            let errorMessage = document.getElementById("error-message");
            let emailInput = document.getElementById("email");

            if (emailPattern.test(email)) {
                emailInput.classList.remove("border-red-500");
                emailInput.classList.add("border-green-500");
                errorMessage.classList.add("hidden");

                fetch('<?php echo BASE_PATH . "/status"; ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'email=' + encodeURIComponent(email)
                    })

                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.text();
                    })
                    .then(data => {
                        console.log('Success:', data);

                        Checkout.showPaymentPage();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        emailInput.classList.remove("border-green-500");
                        emailInput.classList.add("border-red-500");
                        errorMessage.textContent = 'Failed to process email. Please try again.';
                        errorMessage.classList.remove("hidden");
                    });
            } else {
                emailInput.classList.remove("border-green-500");
                emailInput.classList.add("border-red-500");
                errorMessage.classList.remove("hidden");
                errorMessage.textContent = 'Please enter a valid email address.';
            }
        }
    </script>
</body>

</html>