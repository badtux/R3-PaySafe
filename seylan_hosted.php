<?php
// Write some logs
$logger->info('in seylan_hosted.php file');
// $logger->warning('Low disk space');
// $logger->error('Something went wrong', [
//     'exception' => 'ExampleException'
// ]);

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use Ramsey\Uuid\Uuid;

$isURLQueryRequest = ($_SERVER['REQUEST_METHOD'] === 'GET')?true:false;
$willAttemptInit = ($isURLQueryRequest && isset($_GET['currency']) && isset($_GET['amount']) && isset($_GET['orderId']) && isset($_GET['description']) && !isset($_GET['txnId']))?true:false;
$hasInitiated = ($isURLQueryRequest && !$willAttemptInit && isset($_SESSION['txnId']) && isset($_SESSION['sessionId']))?true:false;

function resetPaymentSession($amount, $currency, $orderId, $description){
    $_SESSION['payments'] = []; unset($_SESSION['errorMessage']);
    $txnId = bin2hex(random_bytes(8));
    $_SESSION['payments'][$txnId] = [
        'amount' => $amount,
        'currency' => $currency,
        'description' => $description,
        'orderId' => $orderId,
        'uuid' => Uuid::uuid4()->toString()
    ];

    return $txnId;
}

function initiateCheckout($txnId, $logger) {
    $authString = 'merchant.'.MERCHANT_ID.':'.API_PASSWORD;

    $authHeader = "Authorization: Basic " . base64_encode($authString);
    $endPointUrl = IPG_API_URL.'/'.MERCHANT_ID.'/session';

    $data = [
        "apiOperation" => "INITIATE_CHECKOUT",
        // "checkoutMode" => "WEBSITE",
        "interaction" => [
            "operation" => "PAY",
            // "merchant" => [
            //     "name" => MERCHANT_NAME,
                // "logo" => MERCHANT_LOGO,
                // "url" => MERCHANT_URL,
                // "phone" => MERCHANT_PHONE,
                // "email" => MERCHANT_EMAIL
            ],
            "returnUrl" => REDIRECT_URL,
        ],
        "order" => [
            "currency" => $_SESSION['payments'][$txnId]['currency'],
            "amount" => $_SESSION['payments'][$txnId]['amount'],
            "id" => $_SESSION['payments'][$txnId]['orderId'],
            // "description" => $_SESSION['payments'][$txnId]['description']
        ]
    ];

    $jsonData = json_encode($data);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endPointUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Cache-Control: no-cache",
            $authHeader
        ],
        CURLOPT_SSL_VERIFYPEER => true, 
    ]);

    $response = curl_exec($ch);
    $data = json_decode($response, true);


    $logger->info("cUrl Response: " . $response);

    if (json_last_error() === JSON_ERROR_NONE) {
        if (isset($data['result']) && $data['result'] === 'SUCCESS' && isset($data['session']['id'])) {
            $sessionId = $data['session']['id'];
            $_SESSION['payments'][$txnId]['sessionId'] = $sessionId;
            $logger->info("Checkout initiated successfully. Session ID: " . $sessionId);
            return $sessionId;
        } else {
            $logger->error("Error initiating checkout: " . $response);
            throw new Exception("Error initiating checkout. Please try again.");
        }
    }

    $logger->error("Error while cUrl: " . curl_error($ch));
    throw new Exception("Error in checkout. Please try again or contact ".MERCHANT_NAME.".");
    //{"error":{"cause":"INVALID_REQUEST","explanation":"Authenticated entity not authorised to perform operation for target entity"},"result":"ERROR"}

   //{"checkoutMode":"WEBSITE","merchant":"MPGS00000278","result":"SUCCESS","session":{"id":"SESSION0002845421116G5879485H19","updateStatus":"SUCCESS","version":"5e26913b01"},"successIndicator":"73b1868af8af41f8"}  
    // print_r($response); // Debugging line to see the response
}

try {
    if($willAttemptInit){
        $txnId = resetPaymentSession($_GET['amount'], $_GET['currency'], $_GET['orderId'], $_GET['description']);
        $logger->info("Initialized payment session with txnId: $txnId");

        $sessionId = initiateCheckout($txnId, $logger);
        $_SESSION['sessionId'] = $sessionId;
        $_SESSION['txnId'] = $txnId;

        header("Location: " . BASE_PATH);
        exit;
    }

    if($hasInitiated){
        if(isset($_SESSION['payments'][$_SESSION['txnId']]) && 
            ($_SESSION['payments'][$_SESSION['txnId']]['sessionId'] == $_SESSION['sessionId'])){
            $logger->info('Payment session found for txnId: ' . $_SESSION['txnId']);
        } else {
            throw new Exception("Invalid transaction ID. Please try again.");
        }
    }
}
catch (Exception $e) {
    $logger->error("Exception during checkout initiation: " . $e->getMessage());
    $_SESSION['errorMessage'] = "Error: " . $e->getMessage();
    unset($_SESSION['payments']);

    header("Location: " . BASE_PATH);
    exit;
}

// $errorMessage = null;
// $txnId = isset($_GET['txnId']) ? $_GET['txnId'] : null;

// if (!$txnId || !isset($_SESSION['payments'][$txnId])) {
//     $errorMessage = "Error: order Id or amount is missing. ";
// } else {
//     $payment = $_SESSION['payments'][$txnId];
//     $amount = $payment['amount'];
//     $currency = $payment['currency'];
//     $description = $payment['description'];
//     $orderId = $payment['orderId'];
//     $sessionId = isset($payment['sessionId']) ? $payment['sessionId'] : null;

//     if (!$sessionId) {
//         $errorMessage = "Error: Session could not be created. Please try again.";
//     } elseif (!is_numeric($amount) || $amount <= 0) {
//         $errorMessage = "Error: Invalid amount.";
//     }
// }

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($_SESSION['errorMessage']) ? 'Payment Error | Seylan Bank' : 'Secure Payment | Seylan Bank'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <link href="https://unpkg.com/boxicons@2.1.2/css/boxicons.min.css" rel="stylesheet">
    <style>
        .bg-gradient-red-orange {
            background: linear-gradient(135deg, #dc2626 0%, #ea580c 100%);
        }

        .bg-gradient-red-orange-light {
            background: linear-gradient(135deg, #fef2f2 0%, #fff7ed 100%);
        }

        .bg-gradient-red-orange-hover {
            background: linear-gradient(135deg, #b91c1c 0%, #c2410c 100%);
        }

        .text-red-orange {
            color: #ea580c;
        }

        .border-red-orange {
            border-color: #ea580c;
        }

        .shadow-red-orange {
            box-shadow: 0 10px 15px -3px rgba(220, 38, 38, 0.1), 0 4px 6px -2px rgba(220, 38, 38, 0.05);
        }

        .hover-shadow-red-orange:hover {
            box-shadow: 0 20px 25px -5px rgba(220, 38, 38, 0.1), 0 10px 10px -5px rgba(220, 38, 38, 0.04);
        }
    </style>
    <?php if (!isset($errorMessage)) { ?>
        <script type="text/javascript">
            <?php
            $checkoutJsUrl = APP_LIVE
                ? 'https://seylan.gateway.mastercard.com/static/checkout/checkout.min.js'
                : 'https://test-seylan.mtf.gateway.mastercard.com/static/checkout/checkout.min.js';
            ?>
        </script>

        <script src="<?php echo $checkoutJsUrl; ?>" data-error="errorCallback" data-cancel="cancelCallback"></script>

        <!-- <script type="text/javascript">
            function errorCallback(error) {
                  console.log(JSON.stringify(error));
            }
            function cancelCallback() {
                  console.log('Payment cancelled');
            }
        
            Checkout.configure({
                session: {
                    id:  '<your_initiate_checkout_session_ID>'
                }
            });
        </script> -->

        <script>
            const sessionId = "<?php echo htmlspecialchars($_SESSION['sessionId'] ?? ''); ?>";

            Checkout.configure({
                session: {
                    id: sessionId
                }
            });
            console.log("Loaded sessionId: " + sessionId);
        </script>

    <?php } ?>
</head>

<body class="bg-gradient-red-orange-light min-h-screen flex items-center justify-center p-4">
    <div id="main-container" class="bg-white rounded-2xl shadow-red-orange transition-all duration-300 hover:shadow-red-orange w-full max-w-lg overflow-hidden">
        <div class="bg-gradient-red-orange p-6 text-center">
            <!-- <img src="assets/helpAge_logo.jpg" alt="Logo" class="w-20 h-10 mx-auto mb-2 shadow-2"> -->
            <h1 class="text-2xl font-bold text-white"><?php echo isset($_SESSION['errorMessage']) ? 'Payment Error' : 'Secure Payment'; ?></h1>
            <p class="text-white text-sm">Protected by Seylan Bank</p>
        </div>

        <?php if (isset($_SESSION['errorMessage'])): ?>
            <div class="p-6 text-center">
                <h2 class="text-xl font-bold text-red-600">Error</h2>
                <p class="text-red-500 mt-2"><?php echo htmlspecialchars($_SESSION['errorMessage']); ?></p>
            </div>
        <?php else: ?>
            <div id="main_2">
                <div class="px-6 pt-8">
                    <div class="space-y-6 mb-8">
                        <div class="flex space-x-3">
                            <div class="flex items-center space-x-3 bg-red-50 p-4 rounded-lg flex-1">
                                <i class='bx bx-receipt text-lg text-red-600'></i>
                                <div class="text-left">
                                    <p class="text-sm text-gray-500">Order Reference</p>
                                    <p class="font-bold text-red-600 text-sm pl-4"><?php 
                                        $orderId = $_SESSION['payments'][$_SESSION['txnId']]['orderId'];
                                        echo htmlspecialchars($orderId); 
                                        ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3 bg-orange-50 p-4 rounded-lg flex-1">
                                <i class='bx bx-credit-card text-lg text-orange-600'></i>
                                <div class="text-left">
                                    <p class="text-sm text-gray-500">Total Amount</p>
                                    <p class="font-bold text-orange-600 text-sm pl-4">
                                        <?php
                                        $amount = $_SESSION['payments'][$_SESSION['txnId']]['amount'];
                                        $currency = $_SESSION['payments'][$_SESSION['txnId']]['currency'];

                                        $formattedAmount = (fmod($amount, 1) == 0)
                                            ? number_format($amount, 0, '.', ',')
                                            : number_format($amount, 2, '.', ',');
                                        echo htmlspecialchars($currency) . ' ' . $formattedAmount;
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4 bg-red-50 p-4 rounded-xl">
                            <i class='bx bx-detail text-2xl text-red-600'></i>
                            <div class="text-left">
                                <p class="text-sm text-gray-500">Description</p>
                                <p class="font-bold text-red-600 text-sm pl-4">
                                    <?php 
                                    $description = $_SESSION['payments'][$_SESSION['txnId']]['description'];
                                    echo htmlspecialchars($description); ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4 bg-orange-50 p-4 rounded-xl">
                            <i class='bx bx-envelope text-2xl text-orange-600'></i>
                            <div class="text-left w-full">
                                <p class="text-sm text-gray-500">Your Email</p>
                                <input type="text" id="email" class="border-2 border-gray-300 p-2 rounded-lg w-full focus:border-red-orange" placeholder="Enter your email">
                                <p id="error-message" class="text-red-500 text-sm mt-1 hidden">Please enter a valid email.</p>
                            </div>
                        </div>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" id="termsCheckbox" class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500">
                            <span>By proceeding, I agree to the
                                <a href="https://helpagesl.org/terms.html"
                                    target="_blank"
                                    class="underline text-red-600 hover:text-orange-500 transition duration-300">
                                    terms and conditions
                                </a>
                            </span>
                        </label>
                        <span id="terms-error-message" class="text-red-500 text-sm hidden">You must agree to the Terms and Conditions to proceed.</span>
                    </div>

                    <div class="g-recaptcha mb-5" data-sitekey="<?php echo ROBOT_SITE_KEY; ?>"></div>
                    <p id="recaptcha-error-message" class="text-red-500 text-sm hidden mb-5">Please verify you are not a robot.</p>

                    <button onclick="validateAndProceed()"
                        class="w-full bg-gradient-red-orange hover:bg-gradient-red-orange-hover text-white font-bold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] shadow-lg hover:shadow-red-200 flex items-center justify-center space-x-2">
                        <i class='bx bx-lock-alt text-xl'></i>
                        <span>Proceed to Secure Payment</span>
                    </button>
                </div>
            </div>
            <div id="payment-status" class="hidden mt-6 text-center text-lg font-semibold"></div>
            <div class="flex justify-center">
                <button onclick="window.location.href='https://www.helpagesl.org/'" id="return-to-merchant-btn"
                    class="hidden bg-gradient-red-orange hover:bg-gradient-red-orange-hover text-white font-bold py-4 px-6 mt-2 rounded-xl transition-all duration-300 transform hover:scale-[1.02] shadow-lg hover:shadow-red-200 flex items-center justify-center space-x-2">
                    <span>Return to Merchant</span>
                </button>
            </div>
        <?php endif; ?>
        <div class="mt-3 mb-6 flex items-center justify-center text-sm text-gray-500">
            <div class="flex items-center mr-4">
                <i class='bx bx-shield-quarter text-green-500'></i>
                <span class="ml-2">256-bit SSL Secured Connection</span>
            </div>
            <div class="flex items-center space-x-3">
                <img src="assets/card_logo.png" alt="another logo" class="h-10">
                <img src="assets/bank_logo.png" alt="bank logo" class="h-10">
            </div>
        </div>
    </div>

    <?php if (!isset($_SESSION['errorMessage'])){ ?>
        <script>
            function validateEmail(email) {
                const re = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                return re.test(email);
            }

            function storePaymentDetails() {
                const email = document.getElementById('email').value;
                const amount = "<?php echo htmlspecialchars($amount); ?>";
                const currency = "<?php echo htmlspecialchars($currency); ?>";

                if (email && validateEmail(email)) {
                    localStorage.setItem('email', email);
                    localStorage.setItem('amount', amount);
                    localStorage.setItem('currency', currency);
                    document.getElementById('error-message').classList.add('hidden');
                } else {
                    document.getElementById('error-message').classList.remove('hidden');
                }
            }

            function storePaymentDetails() {
                const email = document.getElementById('email').value;
                const amount = "<?php echo htmlspecialchars($amount); ?>";
                const currency = "<?php echo htmlspecialchars($currency); ?>";

                if (email && validateEmail(email)) {
                    localStorage.setItem('email', email);
                    localStorage.setItem('amount', amount);
                    localStorage.setItem('currency', currency);
                    document.getElementById('error-message').classList.add('hidden');
                } else {
                    document.getElementById('error-message').classList.remove('hidden');
                }
            }
            document.getElementById('email').addEventListener('blur', storePaymentDetails);

            function validateAndProceed() {
                let email = document.getElementById("email").value;
                let emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                let errorMessage = document.getElementById("error-message");
                let emailInput = document.getElementById("email");
                let termsCheckbox = document.getElementById("termsCheckbox");
                let termsErrorMessage = document.getElementById("terms-error-message");
                let recaptchaErrorMessage = document.getElementById("recaptcha-error-message");
                let recaptchaResponse = grecaptcha.getResponse();

                termsErrorMessage.classList.add("hidden");
                if (!termsCheckbox.checked) {
                    termsErrorMessage.classList.remove("hidden");
                    return;
                }

                if (recaptchaResponse.length === 0) {
                    recaptchaErrorMessage.classList.remove("hidden");
                    return false;
                }

                if (emailPattern.test(email)) {
                    emailInput.classList.remove("border-red-500");
                    emailInput.classList.add("border-green-500");
                    errorMessage.classList.add("hidden");

                    console.log('Email stored in browser BEFORE');
                    document.cookie = "userEmail=" + encodeURIComponent(email) + "; path=/; SameSite=Lax";
                    Checkout.showPaymentPage();
                    console.log('Email stored in browser storage:', email);

                } else {
                    emailInput.classList.remove("border-green-500");
                    emailInput.classList.add("border-red-500");
                    errorMessage.classList.remove("hidden");
                    errorMessage.textContent = 'Please enter a valid email address.';
                }
            }
        </script>

        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $recaptchaSecret = ROBOT_SECRET_KEY;
            $recaptchaResponse = $_POST['g-recaptcha-response'];

            $response = file_get_contents(
                "https://www.google.com/recaptcha/api/siteverify?secret=$recaptchaSecret&response=$recaptchaResponse"
            );

            $responseKeys = json_decode($response, true);

            if (!empty($responseKeys["success"]) && $responseKeys["success"] === true) {
                echo "✅ Verification successful! You are not a robot.";
            } else {
                echo "❌ Please verify you are not a robot.";
            }
        }
        ?>
    <?php } ?>
</body>

</html>