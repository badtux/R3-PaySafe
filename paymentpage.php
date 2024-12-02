<?php
//session_start();
require_once 'config/config.php';

if (isset($_GET['price'], $_GET['currency'], $_GET['reference'], $_GET['email'])) {
    $_SESSION['txn'] = [
        'email' => htmlspecialchars($_GET['email']),
        'price' => htmlspecialchars(intval($_GET['price'])),
        'reference' => htmlspecialchars($_GET['reference']),
        'currency' => htmlspecialchars(strtoupper($_GET['currency']))
    ];

    $_SESSION['t'] = md5(serialize($_SESSION['txn']));
    header('Location: /?continue=' . $_SESSION['t']);
    exit;
}

if (!isset($_GET['continue']) || !isset($_SESSION['t']) || !($_GET['continue'] == $_SESSION['t'])) {

    $_SESSION['txn'] = ['email' => false, 'price' => false, 'reference' => false, 'currency' => false];
}

$status = isset($_GET['status']) ? htmlspecialchars($_GET['status']) : null;
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : null;
$txn = $_SESSION['txn'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Form</title>
    <link rel="stylesheet" href="css/custom.css?v=1">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 flex items-center justify-center min-h-screen ">
    <div id="maincontainer" class="bg-white shadow-lg rounded-lg p-8 w-full  md:w-1/2  m-6 flex flex-col lg:flex-row justify-between items-center">
        <div class="p-0 md:pr-10 w -1/2">
            <img class="mx-auto" src="assets/Makley_logo.png" alt="Mulky logo" style="max-width:250px; height:130px;">
            <h2 class="text-xl font-semibold text-center ">Malkey Rent-A-Car</h2>
            <h4 class="text-sm text-center mb-5 text-black-400">Mahesh Mallawaratchie Enterprises Pvt Ltd</h4>
        </div>
        <div class="lg:w-1/2" id="paymentFormContainer">
            <form novalidate id="paymentForm" action="<?= BASE_PATH . '/auth' ?>" method="POST">
                <div class="flex space-x-4 mb-4">
                    <!-- Currency Field -->
                    <div class="w-2/5">
                        <label for="reference" class="block text-sm font-medium text-gray-700 pl-1">Reference</label>
                        <input type="text" name="reference" id="reference"
                            value="<?= $txn['reference'] ?>"
                            required class="error-message mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8"
                            <?= $txn['reference'] ? 'readonly' : '' ?> />
                        <span class="error-message validation-message text-red-500 text-xs mt-1"></span>
                    </div>

                    <div class="w-1/5">
                        <label for="currency" class="block text-sm font-medium text-gray-700 pl-1">Currency</label>

                        <?php if (isset($txn['currency']) && ($txn['currency']) !== false): ?>
                            <input type="text" name="currency" id="currency"
                                value="<?= htmlspecialchars($txn['currency'], ENT_QUOTES, 'UTF-8') ?>"
                                readonly
                                class=' mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8 text-sm sm:text-xs  cursor-not-allowed'>
                        <?php else: ?>
                            <select id="currency" name="currency" required
                                class='mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8 text-sm sm:text-xs'>
                                <option value="LKR" <?= isset($txn['currency']) && $txn['currency'] === 'LKR' ? 'selected' : '' ?>>LKR</option>
                                <option value="USD" <?= isset($txn['currency']) && $txn['currency'] === 'USD' ? 'selected' : '' ?>>USD</option>
                            </select>
                        <?php endif; ?>

                    </div>
                    <div class="w-2/5">
                        <label for="price" class="block text-sm font-medium text-gray-700 pl-1">Amount</label>
                        <input required type="text" name="price" id="price"
                            value="<?= isset($txn['price']) ? $txn['price'] : '' ?>"
                            class="error-message mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8"
                            <?= isset($txn['price']) && $txn['price'] ? 'readonly' : '' ?>
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                            style="appearance: none !important; -webkit-appearance: none !important; -moz-appearance: none !important;" />
                        <span class="error-message validation-message text-red-500 text-xs mt-1"></span>
                    </div>

                </div>
                <div class="flex space-x-4 mb-4">
                    <!-- Card holder name -->
                    <div class="w-1/2">
                        <label for="name" class="block text-sm font-medium text-gray-700 pl-1">Name</label>
                        <input type="text" id="name" name="name"
                            required class="error-message mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8" />
                        <span class="error-message validation-message text-red-500 text-xs mt-1"></span>
                    </div>

                    <!-- Email Field -->
                    <div class="w-1/2">
                        <label for="email" class="block text-sm font-medium text-gray-700 pl-1">Email</label>
                        <input type="email" id="email" name="email"
                            value="<?= isset($txn['email']) ? $txn['email'] : '' ?>"
                            required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8" />
                        <span class=" error-message  text-red-500 text-xs mt-1" id="emailError"></span>

                    </div>
                </div>

                <div class="flex space-x-4 mb-6">
                    <!-- Credit Card Number Field -->
                    <div class="w-full">
                        <label for="card_number" class="block text-sm font-medium text-gray-700 pl-1">Credit Card Number</label>
                        <input id="card_number" name="card_number" type="text" maxlength="19" autocomplete="off" required autofocus
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8" />
                        <span class=' error-message validation-message text-red-500 text-xs mt=1' id='card_number_msg'></span>
                    </div>
                </div>

                <!-- Expiry Date Section -->
                <div class="flex space-x-4 mb-6">
                    <div class="w-full">
                        <label for="cc-exp" class="block text-sm font-medium text-gray-700 pl-1">Expiry Date</label>
                        <div class="flex space-x-4">

                            <!-- Month Dropdown -->
                            <div class="w-1/2">
                                <select id="cc-exp-month" name="exp_month" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8 text-sm sm:text-xs">
                                    <option value="" disabled selected>MM</option>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>"><?= date('m', mktime(0, 0, 0, $i, 1)) ?></option>
                                    <?php endfor; ?>

                                </select>
                                <span class="error-message validation-message text-red-500 text-xs mt-1"></span>
                            </div>
                            <!-- Year Dropdown -->
                            <div class="w-1/2">
                                <select id="cc-exp-year" name="exp_year" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8 text-sm sm:text-xs">
                                    <option value="" disabled selected>YY</option>
                                    <?php for ($year = date('Y'); $year <= date('Y') + 10; $year++): ?>
                                        <option value="<?= substr($year, -2) ?>"><?= substr($year, -2) ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="error-message validation-message text-red-500 text-xs mt-1"></span>
                            </div>

                        </div>
                    </div>
                    <div class="w-2/5">
                        <label for="cvv" class="block text-sm font-medium text-gray-700 pl-1">CVV</label>
                        <input required type="text" name="cvv" id="cvv"
                            maxlength="3" autocomplete="off"
                            class="error-message mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:ring-blue-500 p-2 h-8"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                            style="appearance: none; -webkit-appearance: none; -moz-appearance: none;" />
                        <span class="error-message validation-message text-red-500 text-xs mt-1" id="cvv_msg"></span>
                    </div>

                </div>
                <div class=" fixed sticky top-0 z-10 bg-white py-4">
                    <div>
                        <input type="submit" value="Pay Now"
                            class="mt-5 w-full bg-blue-600 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-md transition duration-200 cursor-pointer h-8" />
                    </div>

                    <div class="logos flex flex-wrap justify-center gap-4 mt-4">
                    <img src="assets/sponser.png" alt="sponser logo" />
                    </div>
                </div>

        </div>

    </div>
    </div>
    </form>

    </div>

    <!-- Notification Message -->

    <div id="notificationMessage" class="
            w-full max-w-md p-4 bg-white rounded-lg hidden  mx-auto">

        <div class="p-0 md:pr-10 w -1/2">
            <img class="mx-auto" src="assets/Makley_logo.png" alt="Malkey logo" style="max-width:250px; height:130px;">
            <h2 class="text-xl font-semibold text-center ">Malkey Rent-A-Car</h2>
            <h4 class="text-sm text-center mb-4  text-black-400">Mahesh Mallawaratchie Enterprises Pvt Ltd</h4>
        </div>
        <p class="
            text-center text-lg font-semibold mt-4"
            id='messageText'></p>
        <a href="https://www.malkey.lk/"
            class="block mt-2 px-6 py-3 text-white font-semibold bg-gradient-to-r from-green-500 to-blue-500 rounded-lg shadow-lg hover:from-blue-600 hover:to-purple-600 transform transition duration-300 ease-in-out hover:scale-105
               text-center">Return to Merchant</a>
    </div>
    </div>

    <!-- Scripts -->
    <script src="//ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script>
        const pubkey_lkr = '<?php echo SMPLY_LKR_PUBKEY; ?>';
        const pubkey_usd = '<?php echo SMPLY_USD_PUBKEY; ?>';
    </script>
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