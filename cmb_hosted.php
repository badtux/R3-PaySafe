<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once('config/config.php');
require 'vendor/autoload.php';


use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;

// Debug: show all errors and catch output
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Ensure fatal errors return JSON for save_email POSTs
register_shutdown_function(function() {
    $err = error_get_last();
    if (!$err) return;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'save_email')) {
        // Log the fatal error
        $msg = isset($err['message']) ? $err['message'] : 'shutdown';
        $file = isset($err['file']) ? $err['file'] : '';
        $line = isset($err['line']) ? $err['line'] : '';
        error_log('save_email fatal: ' . $msg . ' in ' . $file . ' on line ' . $line);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        // clear any output and return JSON
        while (ob_get_level()) { ob_end_clean(); }
        echo json_encode(['ok' => false, 'error' => 'FatalError', 'detail' => $msg . ' in ' . $file . ':' . $line]);
    }
});

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_email') {
    // turn off display of errors during POST response to keep JSON clean
    ini_set('display_errors', 0);

    header('Content-Type: application/json');

    $debugOutput = ob_get_clean();

    $orderIdPost = trim((string)($_POST['orderId'] ?? ''));
    $emailPost = (string)($_POST['email'] ?? '');
    $emailPost = filter_var($emailPost, FILTER_SANITIZE_EMAIL);

    // If orderId not provided in POST, try to get it from txnId in URL or from session
    if ($orderIdPost === '') {
        $txnIdFromGet = isset($_GET['txnId']) ? (string)$_GET['txnId'] : '';
        if ($txnIdFromGet && isset($_SESSION['payments'][$txnIdFromGet]['orderId'])) {
            $orderIdPost = (string)$_SESSION['payments'][$txnIdFromGet]['orderId'];
            error_log('save_email: orderId taken from session payments for txnId ' . $txnIdFromGet . ' => ' . $orderIdPost);
        } elseif (!empty($_SESSION['orderId'])) {
            $orderIdPost = (string)$_SESSION['orderId'];
            error_log('save_email: orderId taken from session.orderId => ' . $orderIdPost);
        }
    }

    if ($orderIdPost === '') {
        error_log('save_email: Missing orderId. POST=' . json_encode($_POST) . ' SESSION=' . json_encode(array_intersect_key($_SESSION, ['orderId'=>1,'payments'=>1])));
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing orderId']);
        exit;
    }

    if ($emailPost === '' || filter_var($emailPost, FILTER_VALIDATE_EMAIL) === false) {
        error_log('save_email: Invalid email "' . $emailPost . '" for orderId "' . $orderIdPost . '". POST=' . json_encode($_POST));
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid email']);
        exit;
    }

    try {
        $client = new Client(DATABASE_URL);
        $dbName = DB;
        $collName = COLLECTION;
        $collection = $client->$dbName->$collName;

        $result = $collection->updateMany(
            ['orderId' => $orderIdPost],
            ['$set' => ['email' => $emailPost, 'updatedAt' => new UTCDateTime()]]
        );

        // Log success to error log for visibility
        error_log(sprintf('save_email: Updated orderId=%s email=%s matched=%d modified=%d',
            $orderIdPost, $emailPost, $result->getMatchedCount(), $result->getModifiedCount()
        ));

        echo json_encode([
            'ok' => true,
            'db' => $dbName,
            'collection' => $collName,
            'orderId' => $orderIdPost,
            'matched' => $result->getMatchedCount(),
            'modified' => $result->getModifiedCount(),
            'debugOutput' => $debugOutput,
        ]);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('save_email: Error updating email for orderId ' . $orderIdPost . ' email ' . $emailPost . ': ' . $e->getMessage());
        echo json_encode([
            'ok' => false,
            'error' => 'DB update failed',
            'detail' => $e->getMessage(),
            'db' => defined('DB') ? DB : null,
            'collection' => defined('COLLECTION') ? COLLECTION : null,
            'orderId' => $orderIdPost,
            'debugOutput' => $debugOutput,
        ]);
        exit;
    }
}

// include auth/render logic only after POST handling
require 'cmb_hostedAuth.php';

$errorMessage = null;
$txnId = isset($_GET['txnId']) ? $_GET['txnId'] : null;

if (!$txnId || !isset($_SESSION['payments'][$txnId])) {
    $errorMessage = "Error: order Id or amount is missing. ";
} else {
    $payment = $_SESSION['payments'][$txnId];
    $amount = $payment['amount'];
    $currency = $payment['currency'];
    $description = $payment['description'];
    $orderId = $payment['orderId'];
    $sessionId = isset($payment['sessionId']) ? $payment['sessionId'] : null;

    if (!$sessionId) {
        $errorMessage = "Error: Session could not be created. Please try again.";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $errorMessage = "Error: Invalid amount.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($errorMessage) ? 'Payment Error | Commercial Bank' : 'Secure Payment | Commercial Bank'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.2/css/boxicons.min.css" rel="stylesheet">
    <?php if (!isset($errorMessage)): ?>
        <!-- <script src="https://cbcmpgs.gateway.mastercard.com/checkout/version/57/checkout.js"></script> -->

        <script src="https://cbcmpgs.gateway.mastercard.com/checkout/version/57/checkout.js"
            data-error="errorCallback"
            data-cancel="cancelCallback"
            data-timeout="timeoutCallback"></script>

        <script type="text/javascript">
            function errorCallback(error) {
                console.log(JSON.stringify(error));
                alert('An error occurred during the payment process. Please try again.');
                window.location.href = "https://www.malkey.lk";
            }

            function cancelCallback() {
                console.log("Payment cancelled");
                alert("Payment process was cancelled.");
                window.location.href = "https://www.malkey.lk";
            }

            function timeoutCallback() {
                console.log("Payment timed out");
                alert("The payment session has expired.");
                window.location.href = "http://paymentgateway.loc/cmb?#__hc-action-timeout";
            }

            const sessionId = "<?php echo htmlspecialchars($sessionId ?? ''); ?>";

            Checkout.configure({
                session: {
                    id: sessionId
                },
                interaction: {
                    displayControl: {
                        billingAddress: 'HIDE',
                        customerEmail: 'HIDE',
                        orderSummary: 'SHOW',
                        shipping: 'HIDE'
                    }
                }
            });
        </script>

    <?php endif; ?>
</head>

<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen flex items-center justify-center p-4">
    <div id="main-container" class="bg-white rounded-2xl shadow-2xl transition-all duration-300 hover:shadow-xl w-full max-w-lg overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 text-center">
            <img src="https://d8asu6slkrh4m.cloudfront.net/2013/04/malkey-logo.png" alt="Logo" class="w-40 h-19 mx-auto mb-2 filter brightness-0 invert">
            <h1 class="text-2xl font-bold text-blue-100"><?php echo isset($errorMessage) ? 'Payment Error' : 'Secure Payment'; ?></h1>
            <p class="text-blue-100 text-sm">Protected by Commercial Bank</p>
        </div>

        <?php if (isset($errorMessage)): ?>
            <div class="p-6 text-center">
                <h2 class="text-xl font-bold text-red-600">Error</h2>
                <p class="text-red-500 mt-2"><?php echo htmlspecialchars($errorMessage); ?></p>
            </div>
        <?php else: ?>
            <div id="main_2">
                <div class="px-6 pt-8">
                    <div class="space-y-6 mb-8">
                        <div class="flex space-x-3">
                            <div class="flex items-center space-x-3 bg-blue-50 p-4 rounded-lg flex-1">
                                <i class='bx bx-receipt text-lg text-blue-600'></i>
                                <div class="text-left">
                                    <p class="text-sm text-gray-500">Order Reference</p>
                                    <p class="font-bold text-blue-600 text-sm pl-4"><?php echo htmlspecialchars($orderId); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3 bg-blue-50 p-4 rounded-lg flex-1">
                                <i class='bx bx-credit-card text-lg text-blue-600'></i>
                                <div class="text-left">
                                    <p class="text-sm text-gray-500">Total Amount</p>
                                    <p class="font-bold text-blue-600 text-sm pl-4">
                                        <?php
                                        $formattedAmount = (fmod($amount, 1) == 0)
                                            ? number_format($amount, 0, '.', ',')
                                            : number_format($amount, 2, '.', ',');
                                        echo htmlspecialchars($currency) . ' ' . $formattedAmount;
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4 bg-blue-50 p-4 rounded-xl">
                            <i class='bx bx-detail text-2xl text-blue-600'></i>
                            <div class="text-left">
                                <p class="text-sm text-gray-500">Description</p>
                                <p class="font-bold text-blue-600 text-sm pl-4"><?php echo htmlspecialchars($description); ?></p>
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
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" id="termsCheckbox" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span>I agree to the
                                <a href="https://www.malkey.lk/terms-conditions.html"
                                    target="_blank"
                                    class="underline text-blue-600 hover:text-red-500 transition duration-300">
                                    Terms and Conditions
                                </a>
                            </span>
                        </label>
                        <span id="terms-error-message" class="text-red-500 text-sm hidden">You must agree to the Terms and Conditions to proceed.</span>
                    </div>
                    <button onclick="validateAndProceed()"
                        class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] shadow-lg hover:shadow-blue-200 flex items-center justify-center space-x-2">
                        <i class='bx bx-lock-alt text-xl'></i>
                        <span>Proceed to Secure Payment</span>
                    </button>
                </div>
            </div>
            <div id="payment-status" class="hidden mt-6 text-center text-lg font-semibold"></div>
            <div class="flex justify-center">
                <button onclick="window.location.href='https://www.malkey.lk/'" id="return-to-merchant-btn"
                    class="hidden bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-4 px-6 mt-2 rounded-xl transition-all duration-300 transform hover:scale-[1.02] shadow-lg hover:shadow-blue-200 flex items-center justify-center space-x-2">
                    <span>Return to Merchant</span>
                </button>
            </div>
        <?php endif; ?>
        <div class="mt-3 mb-6 flex items-center justify-center text-sm text-gray-500">
            <div class="flex items-center">
                <i class='bx bx-shield-quarter text-green-500'></i>
                <span class="mr-2">256-bit SSL Secured Connection</span>
            </div>
            <div>
                <img src="assets/sponser.png" alt="bank logo" class="h-10">
            </div>
        </div>
    </div>

    <?php if (!isset($errorMessage)): ?>
        <script>
            function validateEmail(email) {
                const re = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                return re.test(email);
            }

            function storePaymentDetails() {
                const email = document.getElementById('email').value;

                if (email && validateEmail(email)) {
                    // Do not store email in localStorage or cookies — only validate for now
                    document.getElementById('error-message').classList.add('hidden');
                } else {
                    document.getElementById('error-message').classList.remove('hidden');
                }
            }

            document.getElementById('email').removeEventListener('blur', storePaymentDetails);
            document.getElementById('email').addEventListener('blur', storePaymentDetails);

            // Update email in DB (POST to same page)
            async function updateEmailInDB(orderId, email) {
                // Build url-encoded body, send uuid instead of orderId if available
                const uuid = <?php echo json_encode(isset($_SESSION['uuid']) ? $_SESSION['uuid'] : ($_SESSION['payments'][$txnId ?? ''] ?? null)); ?>;
                const body = 'action=save_email&uuid=' + encodeURIComponent(uuid) + '&email=' + encodeURIComponent(email);

                // Use dedicated endpoint to avoid HTML redirects
                const endpoint = 'paysafe/cmb/save_email.php';

                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                    },
                    body: body,
                    credentials: 'same-origin'
                });

                // Read response text once
                const text = await res.text();
                let json = null;
                if (text) {
                    try {
                        json = JSON.parse(text);
                    } catch (err) {
                        console.error('Invalid JSON response from server:', text);
                        // Show full server response to user for debugging
                        alert('Server returned invalid JSON (raw response shown):\n\n' + text);
                        throw new Error('Invalid JSON response from server');
                    }
                }

                console.log('save_email response:', res.status, json, text);

                if (!res.ok || !json || !json.ok) {
                    const errMsg = (json && (json.error || json.detail)) ? ((json.error || '') + (json.detail ? ': ' + json.detail : '')) : ('save_email failed: ' + (text ? text.substring(0,1000) : 'empty response'));
                    throw new Error(errMsg);
                }

                return json;
            }


            async function validateAndProceed() {
                const email = document.getElementById("email").value;
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const errorMessage = document.getElementById("error-message");
                const emailInput = document.getElementById("email");
                const termsCheckbox = document.getElementById("termsCheckbox");
                const termsErrorMessage = document.getElementById("terms-error-message");

                termsErrorMessage.classList.add("hidden");
                if (!termsCheckbox.checked) {
                    termsErrorMessage.classList.remove("hidden");
                    return;
                }

                if (!emailPattern.test(email)) {
                    emailInput.classList.remove("border-green-500");
                    emailInput.classList.add("border-red-500");
                    errorMessage.classList.remove("hidden");
                    errorMessage.textContent = 'Please enter a valid email address.';
                    return;
                }

                emailInput.classList.remove("border-red-500");
                emailInput.classList.add("border-green-500");
                errorMessage.classList.add("hidden");

                const orderId = <?php echo json_encode($orderId); ?>;

                try {
                    await updateEmailInDB(orderId, email);
                } catch (e) {
                    console.error('save_email error', e);
                    alert('Unable to save email: ' + e.message);
                    return;
                }

                Checkout.showPaymentPage();
            }
        </script>
    <?php endif; ?>
</body>

</html>