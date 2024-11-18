<?php
session_start();


// Fetch URL parameters
$status = isset($_GET['status']) ? htmlspecialchars($_GET['status']) : null;
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : null;
$email = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '';
$price = isset($_GET['price']) ? htmlspecialchars($_GET['price']) : '';
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Form</title>
    <link rel="stylesheet" href="css/custom.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 flex items-center justify-center min-h-screen ">
    <div class="bg-white shadow-lg rounded-lg p-8 w-full max-w-lg m-6">
        <img class="mx-auto mb-4" src="assets/combank.png" alt="Acquiring Bank Logo" style="max-width:250px; height:60px;">
        <h2 class="text-xl font-semibold text-center mb-6">COMBANK MPGS Payment Gateway</h2>

        <div id="paymentFormContainer">
            <form id="paymentForm" action="config/auth.php" method="POST">
                <!-- Email and Amount in One Row -->
                <div class="flex space-x-4 mb-6">
                    <div class="w-1/2">
                        <label for="email" class="block text-sm font-medium text-gray-700">Email:</label>
                        <input type="email" id="email" name="email"
                            value="<?= isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '' ?>"
                            required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2" />
                        <span class="validation-message text-red-500 text-xs mt-1" id="emailError"></span>
                    </div>
                    <div class="w-1/2">
                        <label for="price" class="block text-sm font-medium text-gray-700">Amount (USD):</label>
                        <input type="text" name="price" id="price"
                            value="<?= isset($_GET['price']) ? htmlspecialchars($_GET['price']) : '' ?>"
                            required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2" />
                    </div>
                </div>

                <!-- Credit Card Number, Expiry Date, and CVV in One Row -->
                <div class="flex space-x-4 mb-6">
                    <!-- Credit Card Number Field -->
                    <div class="w-full">
                        <label for="card_number" class="block text-sm font-medium text-gray-700">Credit Card Number:</label>
                        <input id="card_number" name="card_number" type="text" maxlength="20" autocomplete="off" required autofocus
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2" />
                        <span class="validation-message text-red-500 text-xs mt-1" id="card_number_msg"></span>
                    </div>
                </div>

                <!-- Expiry Date Section -->
                <div class="flex space-x-4 mb-6">
                    <div class="w-full">
                        <label for="cc-exp-month" class="block text-sm font-medium text-gray-700">Expiry Date:</label>
                        <div class="flex space-x-4">
                            <select id="cc-exp-month" name="exp_month" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2">
                                <option value="">Month</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>"><?= date('M', mktime(0, 0, 0, $i, 1)) ?></option>
                                <?php endfor; ?>
                            </select>
                            <select id="cc-exp-year" name="exp_year" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2">
                                <option value="">Year</option>
                                <?php for ($year = date('Y'); $year <= date('Y') + 10; $year++): ?>
                                    <option value="<?= substr($year, -2) ?>"><?= $year ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <!-- CVV Section -->
                    <div class="">
                        <label for="cvv" class="block text-sm font-medium text-gray-700">CVV:</label>
                        <input id="cvv" name="cvv" type="text" maxlength="4" autocomplete="off" required
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2" />
                        <span class="validation-message text-red-500 text-xs mt-1" id="cvv_msg"></span>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="mb-6">
                    <input type="submit" value="Pay Now"
                        class="w-full bg-blue-600 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-md transition duration-200 cursor-pointer" />
                </div>

                <!-- Logos -->
                <div class="logos flex flex-wrap justify-center gap-4">
                    <img src="assets/all.jpg" alt="" class="max-w-full sm:w-auto">
                    <img src="assets/card_acceptancelogo.jpg" alt="" class="max-w-full sm:w-auto">
                    <img src="assets/combank.png" alt="" class="max-w-full sm:w-auto">
                </div>

            </form>
        </div>

        <!-- Notification Message -->
        <div id="notificationMessage" class="
            w-full max-w-md p-4 bg-white rounded-lg hidden shadow-lg mt-6 mx-auto">
            <p class="
            text-center text-lg font-semibold"
                id='messageText'></p>
            <a href="/"
                class="
               block mt-4 px-6 py-3 text-white font-semibold bg-gradient-to-r from-green-500 to-blue-500 rounded-lg shadow-lg hover:from-blue-600 hover:to-purple-600 transform transition duration-300 ease-in-out hover:scale-105
               text-center">Return to Home</a>
        </div>
    </div>

    <!-- Scripts -->
    <script src="//ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src='js/form.js'></script>
    <script src="//www.simplify.com/commerce/v1/simplify.js"></script>

    <!-- Handle URL Parameters -->
    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const message = urlParams.get('message');

        if (status && message) {
            $('#paymentFormContainer').hide();
            $('#notificationMessage').removeClass('hidden');
            $('#messageText').text(decodeURIComponent(message));

            if (status === 'APPROVED') {
                $('#messageText').addClass('text-green-500');
            } else {
                $('#messageText').addClass('text-red-500');
            }

            window.history.replaceState({}, document.title, window.location.pathname);
        }
    </script>

</body>

</html>