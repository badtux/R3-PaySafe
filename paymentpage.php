<?php
session_start();

// Fetch URL parameters
$status = isset($_GET['status']) ? htmlspecialchars($_GET['status']) : null;
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : null;
$email = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '';
$price = isset($_GET['price']) ? htmlspecialchars($_GET['price']) : '';
$reference = isset($_GET['reference']) ? htmlspecialchars($_GET['reference']) : '';
$currency = isset($_GET['currency']) ? htmlspecialchars($_GET['currency']) : 'LKR';
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

    <div id="maincontainer" class="bg-white shadow-lg rounded-lg p-8 w-full  md:w-1/2  m-6 flex flex-col lg:flex-row justify-between items-center">
        <div class="p-0 md:pr-10 w -1/2">
            <img class="mx-auto mb-1" src="https://d8asu6slkrh4m.cloudfront.net/2013/04/malkey-logo.png" alt="Acquiring Bank Logo" style="max-width:250px; height:60px;">
            <h2 class="text-xl font-semibold text-center ">Malkey Rent-A-Car</h2>
            <h4 class="text-xl text-center mb-4 mt-2 text-black-400">Mahesh Mallawaratchie Enterprises Pvt Ltd</h4>
        </div>
        <div class="lg:w-1/2" id="paymentFormContainer">
            <form id="paymentForm" action="config/auth.php" method="POST">
                <div class="flex space-x-4 mb-4">
                    <!-- Currency Field -->
                    <div class="w-3/8">
                        <label for="ref" class="block text-sm font-medium text-gray-700 pl-1">Reference:</label>
                        <input type="text" name="reference" id="reference"
                            value="<?= $reference ?>"
                            required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8"
                            <?= $reference ? 'readonly' : '' ?> />
                    </div>

                    <div class="w-2/8">
                        <label for="currency" class="block text-sm font-medium text-gray-700 pl-1">Currency:</label>
                        <select id="currency" name="currency" required
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 text-sm h-8"
                            <?= !empty($currency) ? 'disabled' : '' ?>>
                            <option value="LKR" <?= $currency === 'LKR' ? 'selected' : '' ?>>LKR</option>
                            <option value="USD" <?= $currency === 'USD' ? 'selected' : '' ?>>USD</option>
                        </select>
                        <?php if (!empty($currency)): ?>
                            <input type="hidden" name="currency" value="<?= $currency ?>">
                        <?php endif; ?>
                    </div>

                    <!-- Amount Field -->
                    <div class="w-3/8">
                        <label for="price" class="block text-sm font-medium text-gray-700 pl-1">Amount:</label>
                        <input type="text" name="price" id="price"
                            value="<?= $price ?>"
                            required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8"
                            <?= $price ? 'readonly' : '' ?> />
                    </div>
                </div>

                <div class="flex space-x-4 mb-4">
                    <!-- Card holder name -->
                    <div class="w-1/2">
                        <label for="name" class="block text-sm font-medium text-gray-700 pl-1">Name:</label>
                        <input type="text" id="name" name="name"
                            required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8" />
                    </div>

                    <!-- Email Field -->
                    <div class="w-1/2">
                        <label for="email" class="block text-sm font-medium text-gray-700 pl-1">Email:</label>
                        <input type="email" id="email" name="email"
                            value="<?= $email ?>"
                            required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8" />
                        <span class="validation-message text-red-500 text-xs mt-1" id="emailError"></span>
                    </div>
                </div>

                <div class="flex space-x-4 mb-6">
                    <!-- Credit Card Number Field -->
                    <div class="w-full">
                        <label for="card_number" class="block text-sm font-medium text-gray-700 pl-1">Credit Card Number:</label>
                        <input id="card_number" name="card_number" type="text" maxlength="20" autocomplete="off" required autofocus
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8" />
                        <span class='validation-message text-red-500 text-xs mt=1' id='card_number_msg'></span>
                    </div>
                </div>

                <!-- Expiry Date Section -->
                <div class="flex space-x-4 mb-6">
                    <div class="w-full">
                        <label for='cc-exp-month' class='block text-sm font-medium text-gray-700 pl-1'>Expiry Date:</label>
                        <div class='flex space-x-4'>
                            <select id='cc-exp-month' name='exp_month' required
                                class='mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8 text-sm sm:text-xs'>
                                <option value=''>Month</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>"><?= date('M', mktime(0, 0, 0, $i, 1)) ?></option>
                                <?php endfor; ?>
                            </select>
                            <select id='cc-exp-year' name='exp_year' required
                                class='mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8 text-sm sm:text-xs'>
                                <option value=''>Year</option>
                                <?php for ($year = date('Y'); $year <= date('Y') + 10; $year++): ?>
                                    <option value="<?= substr($year, -2) ?>"><?= $year ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>


                    <!-- CVV Section -->
                    <div class="">
                        <label for='cvv' class='block text-sm font-medium text-gray=700 pl=1'>CVV:</label>
                        <input id='cvv' name='cvv' type='text' maxlength='4' autocomplete='off' required
                            class='mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8' />
                        <span class='validation-message text-red=500 text-xs mt=1' id='cvv_msg'></span>
                    </div>
                </div>

                <!-- Submit Button -->
                <div="">
                    <!-- Pay Now Button -->
                    <input type='submit' value='Pay Now'
                        class='w-full bg-blue-600 hover:bg-green-600 text-white font-semibold py=2 px=4 rounded-md transition duration=200 cursor-pointer h-8' />


                    <div class='logos flex flex-wrap justify-center gap=4'>
                        <img src='assets/all.jpg' alt='' class='max-w-full sm:w-auto'>
                        <img src='assets/card_acceptancelogo.jpg' alt='' class='max-w-full sm:w-auto'>
                        <img src='assets/combank.png' alt='' class='max-w-full sm:w-auto'>
                    </div>
        </div>

        <!-- Logos -->

        </form>
    </div>
 
    <!-- Notification Message -->
   
    <div id="notificationMessage" class="
            w-full max-w-md p-4 bg-white rounded-lg hidden  mx-auto">

        <div class=" p-0 md:pr-10 w -1/2
">
            <img class="mx-auto mb-1" src="https://d8asu6slkrh4m.cloudfront.net/2013/04/malkey-logo.png" alt="Acquiring Bank Logo" style="max-width:250px; height:60px;">
            <h2 class="text-xl font-semibold text-center ">Malkey Rent-A-Car</h2>
            <h4 class="text-lg text-center mb-4  text-black-400">Mahesh Mallawaratchie Enterprises Pvt Ltd</h4>
        </div>
        <p class="
            text-center text-lg font-semibold mt-4"
            id='messageText'></p>
        <a href="https://www.malkey.lk/"
            class="block mt-2 px-6 py-3 text-white font-semibold bg-gradient-to-r from-green-500 to-blue-500 rounded-lg shadow-lg hover:from-blue-600 hover:to-purple-600 transform transition duration-300 ease-in-out hover:scale-105
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
            $('#maincontainer').hide();
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